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
	private array $product_ids;
	private LoggerInterface $logger;
	private string $batch_id;
	private string $api_endpoint;
	private string $merchant_store_url;

	/**
	 * Constructor.
	 *
	 * @param string          $api_endpoint       The API endpoint URL for product synchronization.
	 * @param string          $merchant_store_url Primary key to identify the merchant.
	 * @param array           $product_ids        The product IDs to be synced.
	 * @param LoggerInterface $logger             The logger instance for logging sync operations.
	 */
	public function __construct(
		string $api_endpoint,
		string $merchant_store_url,
		array $product_ids,
		LoggerInterface $logger
	) {

		$this->api_endpoint       = $api_endpoint;
		$this->merchant_store_url = $merchant_store_url;
		$this->product_ids        = $product_ids;
		$this->logger             = $logger;
		$this->batch_id           = wp_generate_uuid4();
	}

	/**
	 * Execute the sync job.
	 *
	 * This method performs the complete sync process:
	 * 1. Transforms products into the API payload format
	 * 2. Sends the data to the agentic commerce API
	 * 3. Handles successful responses by marking products as synced
	 * 4. Handles validation errors by marking only affected products with error details
	 * 5. Handles API/network errors by logging and re-throwing exceptions for retry
	 *
	 * @throws RuntimeException When a retryable error occurs during sync.
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
			$this->handle_api_error( $this->product_ids, $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( $status_code === 200 ) {
			$this->handle_successful_response( $response_body );
		} elseif ( $status_code === 400 || $status_code === 422 ) {
			$this->handle_validation_response( $response_body );
		} else {
			$error_msg = "HTTP {$status_code}: {$response_body}";
			$this->handle_api_error( $this->product_ids, $error_msg );
		}
	}

	/**
	 * Handle successful API response.
	 *
	 * Parses the response to check for individual product validation errors,
	 * marks products accordingly, and logs the result.
	 *
	 * @param string $response_body The API response body.
	 */
	private function handle_successful_response( string $response_body ): void {
		$response_data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// If we can't parse the response, mark all as synced anyway (PayPal accepted the request).
			$this->mark_products_synced( $this->product_ids );
			$this->log_sync_success( $this->product_ids, $this->batch_id );
			return;
		}

		$product_results = $response_data['products'] ?? array();

		if ( empty( $product_results ) ) {
			// No per-product results, mark all as synced.
			$this->mark_products_synced( $this->product_ids );
			$this->log_sync_success( $this->product_ids, $this->batch_id );
			return;
		}

		$this->process_product_results( $product_results );
	}

	/**
	 * Handle validation error response (400/422).
	 *
	 * These are validation errors from PayPal, not API failures.
	 * Products should still be marked as synced but with validation error details.
	 * Only the products that actually failed validation get the error annotation.
	 *
	 * @param string $response_body The API response body.
	 */
	private function handle_validation_response( string $response_body ): void {
		$response_data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// Can't parse response, try to extract product indices from raw error text.
			$failed_product_ids = $this->extract_failed_product_ids_from_error( $response_body );
			$this->mark_products_by_validation_result( $failed_product_ids, $response_body );
			return;
		}

		$product_results = $response_data['products'] ?? array();

		if ( ! empty( $product_results ) ) {
			// We have per-product results, process them.
			$this->process_product_results( $product_results );
			return;
		}

		// No per-product results, extract which products failed from error message.
		$error_message = $response_data['message'] ?? $response_body;
		$failed_product_ids = $this->extract_failed_product_ids_from_error( $error_message );
		$this->mark_products_by_validation_result( $failed_product_ids, $error_message );
	}

	/**
	 * Extract product IDs that failed validation from error message.
	 *
	 * Parses error messages like "data/products/0/image_link must pass..." to
	 * identify which products in the batch actually failed validation.
	 *
	 * @param string $error_message The error message to parse.
	 * @return array Array of product IDs that failed validation.
	 */
	private function extract_failed_product_ids_from_error( string $error_message ): array {
		$failed_indices = array();

		// Pattern to match: data/products/{index}/field_name
		if ( preg_match_all( '/data\/products\/(\d+)\//', $error_message, $matches ) ) {
			$failed_indices = array_unique( array_map( 'intval', $matches[1] ) );
		}

		// Map indices to actual product IDs.
		$failed_product_ids = array();
		foreach ( $failed_indices as $index ) {
			if ( isset( $this->product_ids[ $index ] ) ) {
				$failed_product_ids[] = $this->product_ids[ $index ];
			}
		}

		return $failed_product_ids;
	}

	/**
	 * Mark products based on validation results.
	 *
	 * Products that failed validation get error annotations.
	 * Products that passed (or weren't mentioned) get marked as successfully synced.
	 *
	 * @param array  $failed_product_ids Product IDs that failed validation.
	 * @param string $error_message      The validation error message.
	 */
	private function mark_products_by_validation_result( array $failed_product_ids, string $error_message ): void {
		$successfully_synced = array();
		$validation_errors   = array();

		foreach ( $this->product_ids as $product_id ) {
			if ( in_array( $product_id, $failed_product_ids, true ) ) {
				$this->mark_product_with_validation_error( $product_id, $error_message );
				$validation_errors[ $product_id ] = $error_message;
			} else {
				$this->mark_product_synced( $product_id );
				$successfully_synced[] = $product_id;
			}
		}

		// Log results.
		if ( ! empty( $successfully_synced ) ) {
			$this->logger->info(
				sprintf(
					'Agentic Sync Job %s: Successfully synced %d products',
					$this->batch_id,
					count( $successfully_synced )
				),
				array(
					'product_ids' => $successfully_synced,
				)
			);
		}

		if ( ! empty( $validation_errors ) ) {
			$this->logger->warning(
				sprintf(
					'Agentic Sync Job %s: %d products with validation errors',
					$this->batch_id,
					count( $validation_errors )
				),
				array(
					'validation_errors' => $validation_errors,
				)
			);
		}
	}

	/**
	 * Process individual product results from API response.
	 *
	 * Marks each product as synced successfully or with validation errors
	 * based on the API response for that specific product.
	 *
	 * @param array $product_results Array of product results from API.
	 */
	private function process_product_results( array $product_results ): void {
		$successfully_synced = array();
		$validation_errors   = array();

		foreach ( $product_results as $result ) {
			$product_id = $result['product_id'] ?? null;
			$status     = $result['status'] ?? 'unknown';
			$error      = $result['error'] ?? null;

			if ( ! $product_id ) {
				continue;
			}

			if ( $status === 'success' || $status === 'accepted' ) {
				$this->mark_product_synced( $product_id );
				$successfully_synced[] = $product_id;
			} elseif ( $status === 'validation_error' || $error ) {
				$error_message = $error ?? 'Product validation failed';
				$this->mark_product_with_validation_error( $product_id, $error_message );
				$validation_errors[ $product_id ] = $error_message;
			} else {
				// Unknown status, mark as synced with a note.
				$this->mark_product_synced( $product_id );
				$successfully_synced[] = $product_id;
			}
		}

		// Log results.
		if ( ! empty( $successfully_synced ) ) {
			$this->logger->info(
				sprintf(
					'Agentic Sync Job %s: Successfully synced %d products',
					$this->batch_id,
					count( $successfully_synced )
				),
				array(
					'product_ids' => $successfully_synced,
				)
			);
		}

		if ( ! empty( $validation_errors ) ) {
			$this->logger->warning(
				sprintf(
					'Agentic Sync Job %s: %d products with validation errors',
					$this->batch_id,
					count( $validation_errors )
				),
				array(
					'validation_errors' => $validation_errors,
				)
			);
		}
	}

	/**
	 * Handle API or network errors by logging and throwing exception for retry.
	 *
	 * This method handles actual API failures (not validation errors) that should
	 * trigger retry logic. Products are marked with error metadata, and an exception
	 * is thrown to signal Action Scheduler to retry.
	 *
	 * @param array  $product_ids   Product IDs that failed to sync.
	 * @param string $error_message The error message.
	 * @throws RuntimeException When an error occurs during sync.
	 */
	private function handle_api_error( array $product_ids, string $error_message ): void {
		$this->logger->warning(
			sprintf( 'Agentic Sync Job %s: API Error - %s', $this->batch_id, $error_message ),
			array(
				'product_count' => count( $product_ids ),
				'product_ids'   => $product_ids,
			)
		);

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$product->update_meta_data( '_ppcp_agentic_sync_error', $error_message );
			$product->save_meta_data();
		}

		throw new RuntimeException( sprintf( 'Agentic sync failed: %s', $error_message ) );
	}

	/**
	 * Mark a single product as synced successfully.
	 *
	 * @param int $product_id Product ID to mark as synced.
	 */
	private function mark_product_synced( int $product_id ): void {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}

		$timestamp = current_time( 'mysql' );
		$product->update_meta_data( '_ppcp_agentic_last_sync', $timestamp );
		$product->delete_meta_data( '_ppcp_agentic_sync_error' );
		$product->save_meta_data();
	}

	/**
	 * Mark a single product with validation error.
	 *
	 * Products with validation errors are still considered "synced" (the sync
	 * attempt was made), but store the validation error for merchant visibility.
	 *
	 * @param int    $product_id    Product ID.
	 * @param string $error_message Validation error message.
	 */
	private function mark_product_with_validation_error( int $product_id, string $error_message ): void {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}

		$timestamp = current_time( 'mysql' );
		$product->update_meta_data( '_ppcp_agentic_last_sync', $timestamp );
		$product->update_meta_data( '_ppcp_agentic_sync_error', $error_message );
		$product->save_meta_data();
	}

	/**
	 * Mark multiple products as synced by updating their last sync timestamp.
	 *
	 * @param array $product_ids Product IDs to mark as synced.
	 */
	private function mark_products_synced( array $product_ids ): void {
		foreach ( $product_ids as $product_id ) {
			$this->mark_product_synced( $product_id );
		}
	}

	/**
	 * Logs successful sync operation.
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
