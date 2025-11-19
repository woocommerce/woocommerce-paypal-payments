<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion;

use RuntimeException;
use Psr\Log\LoggerInterface;

/**
 * Represents a sync job for sending product data to the agentic commerce API.
 * This class handles the execution of product synchronization operations,
 * including transforming product data, sending requests to the API, and
 * managing success/failure states for products.
 */
class SyncJob {
	/**
	 * The product IDs to be synced.
	 *
	 * @var array
	 */
	private array $product_ids;

	/**
	 * The logger instance for logging sync operations.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * The unique identifier for this sync batch.
	 *
	 * @var string
	 */
	private string $batch_id;

	/**
	 * The API endpoint URL for product synchronization.
	 *
	 * @var string
	 */
	private string $api_endpoint;
	private string $merchant_store_url;

	/**
	 * Constructor.
	 *
	 * @param string          $api_endpoint The API endpoint URL for product synchronization.
	 * @param string          $merchant_store_url Primary key to identify the merchant.
	 * @param array           $product_ids  The product IDs to be synced.
	 * @param LoggerInterface $logger       The logger instance for logging sync operations.
	 */
	public function __construct(
		string $api_endpoint,
		string $merchant_store_url,
		array $product_ids,
		LoggerInterface $logger
	) {

		$this->api_endpoint = $api_endpoint;
		$this->merchant_store_url = $merchant_store_url;
		$this->product_ids  = $product_ids;
		$this->logger       = $logger;
		$this->batch_id     = wp_generate_uuid4();
	}

	/**
	 * Execute the sync job.
	 *
	 * This method performs the complete sync process:
	 * 1. Transforms products into the API payload format
	 * 2. Sends the data to the agentic commerce API
	 * 3. Handles successful responses by marking products as synced
	 * 4. Handles errors by logging and re-throwing exceptions for retry
	 *
	 * @throws RuntimeException When an error occurs during sync, handled by Action Scheduler.
	 */
	public function execute(): void {
		$this->logger->info(
			sprintf( 'Agentic Sync Job %s: Started', $this->batch_id )
		);

		// Transform products for API using the factory.
		$api_products = new ProductsPayload( $this->merchant_store_url, $this->product_ids );
		$api_payload  = $api_products->get_array();

		if ( empty( $api_payload ) ) {
			$this->logger->info(
				sprintf( 'Agentic Sync Job %s: No products', $this->batch_id )
			);

			return;
		}

		// Send payload to API.
		$response = wp_remote_post(
			$this->api_endpoint,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => (string) wp_json_encode(
					array(
						'merchant_url' => $this->merchant_store_url,
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
	 * Handle sync error by logging and marking products with error.
	 *
	 * This method logs the error and updates product metadata to indicate
	 * that the product failed to sync. It then throws an exception to
	 * trigger retry logic in Action Scheduler.
	 *
	 * @param array  $product_ids   Product IDs that failed to sync.
	 * @param string $error_message The error message.
	 * @throws RuntimeException When an error occurs during sync.
	 */
	private function handle_sync_error( array $product_ids, string $error_message ): void {
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

			if ( ! $product ) {
				continue;
			}

			$product->update_meta_data( '_ppcp_agentic_sync_error', $error_message );
			$product->save_meta_data();
		}

		// Let Action Scheduler handle retries by throwing an exception.
		throw new RuntimeException( sprintf( 'Agentic sync failed: %s', $error_message ) );
	}

	/**
	 * Mark products as synced by updating their last sync timestamp.
	 *
	 * This method updates the '_ppcp_agentic_last_sync' meta-field for each
	 * product with the current timestamp, indicating successful synchronization.
	 * It also removes the '_ppcp_agentic_needs_sync' and '_ppcp_agentic_sync_error'
	 * meta-fields to indicate that the product is no longer pending or in an error state.
	 *
	 * @param array $product_ids Product IDs to mark as synced.
	 */
	private function mark_products_synced( array $product_ids ): void {
		// Use WordPress's current_time function with 'mysql' format for consistency.
		$timestamp = current_time( 'mysql' );

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$product->update_meta_data( '_ppcp_agentic_last_sync', $timestamp );
			$product->delete_meta_data( '_ppcp_agentic_needs_sync' );
			$product->delete_meta_data( '_ppcp_agentic_sync_error' );
			$product->save_meta_data();
		}
	}

	/**
	 * Logs successful sync operation.
	 *
	 * This method logs information about a successful sync operation,
	 * including the batch ID and the number of products successfully synced.
	 *
	 * @param array  $product_ids Product IDs that were successfully synced.
	 * @param string $batch_id    The batch ID for logging.
	 */
	private function log_sync_success( array $product_ids, string $batch_id ): void {
		$this->logger->info(
			sprintf(
				'Agentic Sync Job %s: Successfully synced %d products',
				$batch_id,
				count( $product_ids )
			),
			array(
				'product_ids' => $product_ids,
			)
		);
	}
}
