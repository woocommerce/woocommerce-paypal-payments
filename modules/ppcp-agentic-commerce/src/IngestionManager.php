<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use Psr\Log\LoggerInterface;

class IngestionManager {

	private int $batch_size = 50; // API accepts up to 100 products per request
	private IngestionBatchProvider $batch_provider;
	private SyncJobFactory $sync_job_factory;

	public function __construct(
		IngestionBatchProvider $batch_provider,
		SyncJobFactory $sync_job_factory
	) {
		$this->batch_provider   = $batch_provider;
		$this->sync_job_factory = $sync_job_factory;
	}

	public function init() {
		$this->register_hooks();
		$this->schedule_recurring_sync();
	}

	private function register_hooks() {
		// Main sync action
		add_action( 'ppcp_agentic_sync_batch', array( $this, 'process_next_batch' ) );

		// Handle re-sync on product update
		add_action( 'woocommerce_update_product', array( $this, 'mark_product_for_sync' ) );
		add_action( 'woocommerce_product_set_stock', array( $this, 'mark_product_for_sync' ) );
	}

	private function schedule_recurring_sync() {
		if ( ! as_next_scheduled_action( 'ppcp_agentic_sync_batch' ) ) {
			as_schedule_recurring_action(
				time(),
				15 * MINUTE_IN_SECONDS, // Run every 15 minutes.
				'ppcp_agentic_sync_batch',
				array(),
				'ppcp_agentic_sync'
			);
		}
	}

	/**
	 * @throws \Exception
	 * @wp-hook ppcp_agentic_sync_batch
	 */
	public function process_next_batch(): void {
		// Get products needing sync using WooCommerce APIs
		$product_ids = $this->batch_provider->get_batch( $this->batch_size );

		if ( empty( $product_ids ) ) {
			return; // Nothing to sync
		}
		$syncJob = $this->sync_job_factory->create_job( $product_ids );
		$syncJob->execute();
	}

	/**
	 * Mark product for sync when it's updated.
	 *
	 * @wp-hook woocommerce_update_product
	 */
	public function mark_product_for_sync( $product_id ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$product->update_meta_data( '_ppcp_agentic_needs_sync', '1' );
		$product->save_meta_data();
	}
}
