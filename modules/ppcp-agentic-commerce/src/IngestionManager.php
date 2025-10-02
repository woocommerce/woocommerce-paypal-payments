<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce;

use Psr\Log\LoggerInterface;

class IngestionManager {

	private $batch_size = 50; // API accepts up to 100 products per request
	private IngestionBatchProvider $batch_provider;
	private LoggerInterface $logger;

	public function __construct(
		IngestionBatchProvider $batch_provider,
		LoggerInterface $logger
	) {
		$this->batch_provider = $batch_provider;
		$this->logger         = $logger;
	}

	public function init() {
		$this->register_hooks();
		$this->schedule_recurring_sync();
	}

	private function register_hooks() {
		// Main sync action
		add_action( 'ppcp_agentic_sync_batch', array( $this, 'process_next_batch' ) );

		// Real-time triggers
		add_action( 'woocommerce_update_product', array( $this, 'mark_product_for_sync' ) );
		add_action( 'woocommerce_product_set_stock', array( $this, 'mark_product_for_sync' ) );

		// Manual sync trigger
		add_action( 'ppcp_agentic_manual_sync', array( $this, 'trigger_immediate_sync' ) );
	}

	private function schedule_recurring_sync() {
		if ( ! as_next_scheduled_action( 'ppcp_agentic_sync_batch' ) ) {
			as_schedule_recurring_action(
				time(),
				15 * MINUTE_IN_SECONDS, // Run every 15 minutes
				'ppcp_agentic_sync_batch',
				array(),
				'ppcp_agentic_sync'
			);
		}
	}

	public function process_next_batch() {
		// Get products needing sync using WooCommerce APIs
		$product_ids = $this->batch_provider->get_batch( $this->batch_size );

		if ( empty( $product_ids ) ) {
			return; // Nothing to sync
		}
		$syncJob = new SyncJob( $product_ids, $this->logger );
		$syncJob->execute();
	}
}
