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
		$batch_size       = $this->configuration->get_sync_batch_size();
		$resync_timestamp = $this->configuration->get_expired_product_timestamp();
		$stale_date       = gmdate( 'Y-m-d H:i:s', $resync_timestamp );

		// Define meta queries for different product states.
		$meta_fresh = array(
			'key'     => '_ppcp_agentic_last_sync',
			'compare' => 'NOT EXISTS',
		);

		$meta_dirty = array(
			'key'     => '_ppcp_agentic_needs_sync',
			'compare' => 'EXISTS',
		);

		$meta_stale = array(
			'key'     => '_ppcp_agentic_last_sync',
			'value'   => $stale_date,
			'compare' => '<',
			'type'    => 'DATETIME',
		);

		// Get products that have never been synced.
		$batch = $this->get_products( $meta_fresh, array(), $batch_size );

		if ( count( $batch ) >= $batch_size ) {
			return $batch;
		}

		// Get products that have been updated since the last sync.
		$dirty = $this->get_products( $meta_dirty, $batch, $batch_size - count( $batch ) );
		$batch = array_merge( $batch, $dirty );

		if ( count( $batch ) >= $batch_size ) {
			return $batch;
		}

		// Get products that are about to get stale.
		$stale = $this->get_products( $meta_stale, $batch, $batch_size - count( $batch ), true );

		return array_merge( $batch, $stale );
	}

	/**
	 * Get products matching the given meta query criteria.
	 *
	 * @param array $meta_query The meta query criteria.
	 * @param array $exclude    Product IDs to exclude from the query.
	 * @param int   $limit      Maximum number of products to retrieve.
	 * @param bool  $order_by_meta Whether to order results by meta value (for stale products).
	 * @return array Array of product IDs.
	 */
	private function get_products( array $meta_query, array $exclude = array(), int $limit = 0, bool $order_by_meta = false ): array {
		$product_types = $this->configuration->get_supported_product_types();

		$args = array(
			'status'       => ProductStatus::PUBLISH,
			'type'         => $product_types,
			'downloadable' => false,
			'limit'        => $limit > 0 ? $limit : $this->configuration->get_sync_batch_size(),
			'return'       => 'ids',
			'meta_query'   => array( $meta_query ),
		);

		// Add exclusions if provided.
		if ( ! empty( $exclude ) ) {
			$args['exclude'] = $exclude;
		}

		// Add ordering for stale products (oldest first).
		if ( $order_by_meta ) {
			$args['orderby']  = 'meta_value';
			$args['order']    = 'ASC';
			$args['meta_key'] = '_ppcp_agentic_last_sync';
		}

		// phpcs:disable WordPress.DB.SlowDBQuery
		$products = wc_get_products( $args );
		// phpcs:enable WordPress.DB.SlowDBQuery

		assert( is_array( $products ) );

		return $products;
	}
}
