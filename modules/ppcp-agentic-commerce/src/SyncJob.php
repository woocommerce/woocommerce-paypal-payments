<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use Exception;
use Psr\Log\LoggerInterface;

class SyncJob {
	private array $product_ids;
	private LoggerInterface $logger;
	private string $batch_id;
	private string $api_endpoint;

	public function __construct(
		string $api_endpoint,
		array $product_ids,
		LoggerInterface $logger
	) {

		$this->api_endpoint = $api_endpoint;
		$this->product_ids  = $product_ids;
		$this->logger       = $logger;
		$this->batch_id     = wp_generate_uuid4();
	}

	/**
	 * @throws Exception
	 */
	public function execute(): void {

		$this->logger->info(
			sprintf( 'Agentic Sync Job %s: Started', $this->batch_id )
		);

		// Transform products for API
		$api_products = new ProductsPayload( $this->product_ids );
		$api_payload  = $api_products->get_array();
		if ( empty( $api_payload ) ) {
			$this->logger->info(
				sprintf( 'Agentic Sync Job %s: No products', $this->batch_id )
			);
			return;
		}

		// Send to API
		$response = wp_remote_post(
			$this->api_endpoint,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'merchant_url' => home_url(),
						'products'     => $api_payload,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->handle_sync_error( $this->product_ids, $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code === 200 ) {
			$this->mark_products_synced( $this->product_ids );
			$this->log_sync_success( $this->product_ids, $this->batch_id );
		} else {
			$error_msg = "HTTP {$status_code}: " . wp_remote_retrieve_body( $response );
			$this->handle_sync_error( $this->product_ids, $error_msg );
		}
	}

	/**
	 * @throws Exception
	 */
	private function handle_sync_error( $product_ids, $error_message ): void {
		// Log the error.
		$this->logger->warning(
			sprintf( 'Agentic Sync Job %s: Error', $error_message ),
			array(
				'product_count' => count( $product_ids ),
				'product_ids'   => $product_ids,
			)
		);

		// Mark products with error.
		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$product->update_meta_data( '_ppcp_agentic_sync_error', $error_message );
				$product->save_meta_data();
			}
		}

		// Let Action Scheduler handle retries by throwing an exception.
		throw new Exception( sprintf( 'Agentic sync failed: %s', $error_message ) );
	}

	private function mark_products_synced( $product_ids ): void {
		$timestamp = current_time( 'mysql' );

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {

				$product->update_meta_data( '_ppcp_agentic_last_sync', $timestamp );
				$product->delete_meta_data( '_ppcp_agentic_needs_sync' );
				$product->delete_meta_data( '_ppcp_agentic_sync_error' );
				$product->save_meta_data();
			}
		}
	}

	/**
	 * Logs successful sync operation.
	 *
	 * @param array  $product_ids Product IDs that were successfully synced.
	 */
	private function log_sync_success( array $product_ids ): void {
		$this->logger->info(
			sprintf(
				'Agentic Sync Job %s: Successfully synced %d products',
				$this->batch_id ,
				count( $product_ids )
			),
			array(
				'product_ids'   => $product_ids,
			)
		);
	}
}
