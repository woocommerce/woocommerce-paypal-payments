<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion;

use Automattic\WooCommerce\Enums\ProductStatus;

/**
 * Provides a batch of WC_Product IDs eligible for
 * syncing with the agentic commerce product ingestion endpoint
 */
class IngestionBatchProvider {
	private int $stale_timeout_days;
	private array $product_types;

	public function __construct(
		int $stale_timeout_days,
		array $product_types
	) {
		$this->stale_timeout_days = $stale_timeout_days;
		$this->product_types      = $product_types;
	}

	/**
	 * Get a batch of products that need to be synced.
	 *
	 * The batch prioritizes products in this order:
	 * 1. Products that have never been synced
	 * 2. Products that have been updated since last sync
	 * 3. Products that haven't been synced in the configured stale timeout period
	 *
	 * @param int $limit The maximum number of products to include in the batch.
	 *
	 * @return array An array of product IDs that need to be synced.
	 */
	public function get_batch( $limit = 50 ): array {
		// First, get products that have never been synced.
		$batch = wc_get_products(
			array(
				'status'       => ProductStatus::PUBLISH,
				'type'         => $this->product_types,
				'downloadable' => false,
				'limit'        => $limit,
				'return'       => 'ids',
				'meta_query'   => array(
					array(
						'key'     => '_ppcp_agentic_last_sync',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		// If we're already at the limit, return early.
		if ( count( $batch ) >= $limit ) {
			return $batch;
		}

		// If we need more, get products that have been updated since the last sync.
		$dirty_products = wc_get_products(
			array(
				'status'     => 'publish',
				'type'       => $this->product_types,
				'downloadable' => false,
				'limit'      => $limit - count( $batch ),
				'return'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => '_ppcp_agentic_needs_sync',
						'compare' => 'EXISTS',
					),
				),
			)
		);
		// Merge into batch.
		$batch = array_merge( $batch, $dirty_products );

		// If we're now at the limit, return.
		if ( count( $batch ) >= $limit ) {
			return $batch;
		}

		// If we need even more, get stale products (last synced before the timeout)
		$stale_date = date(
			'Y-m-d H:i:s',
			strtotime(
				'-' . $this->stale_timeout_days . ' days'
			)
		);
		$stale_products = wc_get_products(
			array(
				'status'     => 'publish',
				'type'       => $this->product_types,
				'downloadable' => false,
				'limit'      => $limit - count( $batch ),
				'return'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => '_ppcp_agentic_last_sync',
						'value'   => $stale_date,
						'compare' => '<',
						'type'    => 'DATETIME',
					),
				),
				'orderby'    => 'meta_value',
				'order'      => 'ASC',
				'meta_key'   => '_ppcp_agentic_last_sync',
			)
		);

		// Merge and return.
		return array_merge( $batch, $stale_products );
	}
}
