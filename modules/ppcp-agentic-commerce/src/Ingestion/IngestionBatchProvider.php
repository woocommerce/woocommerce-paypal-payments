<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion;

use Automattic\WooCommerce\Enums\ProductStatus;

use WooCommerce\PayPalCommerce\AgenticCommerce\Config\IngestionConfiguration;

/**
 * Provides a batch of WC_Product IDs eligible for
 * syncing with the agentic commerce product ingestion endpoint
 */
class IngestionBatchProvider {
	private IngestionConfiguration $configuration;

	public function __construct( IngestionConfiguration $configuration ) {
		$this->configuration = $configuration;
	}

	/**
	 * Get a batch of products that need to be synced.
	 *
	 * The batch prioritizes products in this order:
	 * 1. Products that have never been synced
	 * 2. Products that have been updated since last sync
	 * 3. Products that haven't been synced in the configured stale timeout period
	 *
	 * @return array An array of product IDs that need to be synced.
	 */
	public function get_batch(): array {
		$product_types    = $this->configuration->get_supported_product_types();
		$batch_size       = $this->configuration->get_sync_batch_size();
		$resync_timestamp = $this->configuration->get_expired_product_timestamp();

		// phpcs:disable WordPress.DB.SlowDBQuery
		// First, get products that have never been synced.
		$batch = wc_get_products(
			array(
				'status'       => ProductStatus::PUBLISH,
				'type'         => $product_types,
				'downloadable' => false,
				'limit'        => $batch_size,
				'return'       => 'ids',
				'meta_query'   => array(
					array(
						'key'     => '_ppcp_agentic_last_sync',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		assert( is_array( $batch ) );

		// If we're already at the limit, return early.
		if ( count( $batch ) >= $batch_size ) {
			return $batch;
		}

		// If we need more, get products that have been updated since the last sync.
		$dirty_products = wc_get_products(
			array(
				'status'       => 'publish',
				'type'         => $product_types,
				'downloadable' => false,
				'limit'        => $batch_size - count( $batch ),
				'return'       => 'ids',
				'meta_query'   => array(
					array(
						'key'     => '_ppcp_agentic_needs_sync',
						'compare' => 'EXISTS',
					),
				),
			)
		);
		assert( is_array( $dirty_products ) );
		// Merge into batch.
		$batch = array_unique( array_merge( $batch, $dirty_products ) );

		// If we're now at the limit, return.
		if ( count( $batch ) >= $batch_size ) {
			return $batch;
		}

		// If we need even more, include products that are about to get stale.
		$stale_date = gmdate( 'Y-m-d H:i:s', $resync_timestamp );

		$stale_products = wc_get_products(
			array(
				'status'       => 'publish',
				'type'         => $product_types,
				'downloadable' => false,
				'limit'        => $batch_size - count( $batch ),
				'return'       => 'ids',
				'meta_query'   => array(
					array(
						'key'     => '_ppcp_agentic_last_sync',
						'value'   => $stale_date,
						'compare' => '<',
						'type'    => 'DATETIME',
					),
				),
				'orderby'      => 'meta_value',
				'order'        => 'ASC',
				'meta_key'     => '_ppcp_agentic_last_sync',
			)
		);
		assert( is_array( $stale_products ) );
		// phpcs:enable WordPress.DB.SlowDBQuery

		// Merge and return.
		return array_unique( array_merge( $batch, $stale_products ) );
	}
}
