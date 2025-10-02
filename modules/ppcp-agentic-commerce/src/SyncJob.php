<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use Exception;
use Psr\Log\LoggerInterface;

class SyncJob {
	private array $product_ids;
	/**
	 * TODO turn into parameter
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://d.joinhoney.com/webhooks/products';
	private LoggerInterface $logger;
	private string $batch_id;

	public function __construct(
		array $product_ids,
		LoggerInterface $logger
	) {

		$this->product_ids = $product_ids;
		$this->logger      = $logger;
		$this->batch_id = wp_generate_uuid4();

	}

	/**
	 * @throws Exception
	 */
	public function execute() {
		// Transform products for API
		$api_products = new ProductsPayload( $this->product_ids );
		$api_payload  = $api_products->get_array();
		if ( empty( $api_payload ) ) {
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
				'body'    => json_encode(
					array(
						'merchant_url' => home_url(),
						'products'     => $api_payload,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->handle_sync_error( $this->product_ids, $response->get_error_message() );

			return;
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
		// Log the error
		$this->logger->info(
			sprintf( 'Agentic Sync Error: %s', $error_message ),
			array(
				'batch_id'      => $this->batch_id,
				'product_count' => count( $product_ids ),
				'product_ids'   => $product_ids,
				'timestamp'     => current_time( 'mysql' ),
			)
		);

		// Mark products with error (for debugging)
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
				$hash = $this->calculate_product_hash( $product );

				$product->update_meta_data( '_ppcp_agentic_last_sync', $timestamp );
				$product->update_meta_data( '_ppcp_agentic_sync_hash', $hash );
				$product->delete_meta_data( '_ppcp_agentic_sync_error' ); // Clear errors.
				$product->save_meta_data();
			}
		}
	}

	private function calculate_product_hash( $product ): string {
		$data = array(
			'name'              => $product->get_name(),
			'description'       => $product->get_description(),
			'short_description' => $product->get_short_description(),
			'price'             => $product->get_price(),
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'stock_status'      => $product->get_stock_status(),
			'stock_quantity'    => $product->get_stock_quantity(),
			'sku'               => $product->get_sku(),
			'weight'            => $product->get_weight(),
			'dimensions'        => $product->get_dimensions( false ),
			'category_ids'      => $product->get_category_ids(),
			'tag_ids'           => $product->get_tag_ids(),
			'image_id'          => $product->get_image_id(),
			'gallery_image_ids' => $product->get_gallery_image_ids(),
			'modified'          => $product->get_date_modified()->getTimestamp(),
		);

		return md5( serialize( $data ) );
	}

	/**
	 * Logs successful sync operation.
	 *
	 * @param array $product_ids Product IDs that were successfully synced.
	 * @param string $batch_id Unique identifier for this batch.
	 */
	private function log_sync_success( array $product_ids, string $batch_id ): void {
		$this->logger->info(
			sprintf( 'Successfully synced %d products in batch %s', count( $product_ids ), $batch_id ),
			array(
				'batch_id'      => $batch_id,
				'product_count' => count( $product_ids ),
				'product_ids'   => $product_ids
			)
		);
	}
}
