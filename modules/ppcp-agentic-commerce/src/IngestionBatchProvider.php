<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

class IngestionBatchProvider {
	public function get_batch( $limit = 50 ): array {
		// First, get products that have never been synced
		$never_synced = wc_get_products(
			array(
				'status'     => 'publish',
				'limit'      => $limit,
				'return'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => '_ppcp_agentic_last_sync',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		if ( count( $never_synced ) >= $limit ) {
			return $never_synced;
		}

		// If we need more, get stale products (synced > 24 hours ago)
		$remaining = $limit - count( $never_synced );

		$stale_products = wc_get_products(
			array(
				'status'     => 'publish',
				'limit'      => $remaining,
				'return'     => 'ids',
				'meta_query' => array(
					array(
						'key'     => '_ppcp_agentic_last_sync',
						'value'   => date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) ),
						'compare' => '<',
						'type'    => 'DATETIME',
					),
				),
				'orderby'    => 'meta_value',
				'order'      => 'ASC',
				'meta_key'   => '_ppcp_agentic_last_sync',
			)
		);

		// Combine and return
		return array_merge( $never_synced, $stale_products );
	}
}
