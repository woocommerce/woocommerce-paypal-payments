<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion;

use Psr\Log\LoggerInterface;

/**
 * Manages the ingestion process for agentic commerce.
 * This class handles scheduling sync jobs and marking products for sync.
 */
class IngestionManager {

	/**
	 * The batch size for sync operations.
	 *
	 * @var int
	 */
	private int $batch_size = 50;
	private IngestionBatchProvider $batch_provider;
	private SyncJobFactory $sync_job_factory;

	/**
	 * Constructor.
	 *
	 * @param IngestionBatchProvider $batch_provider The batch provider for getting products to sync.
	 * @param SyncJobFactory         $sync_job_factory The factory for creating sync jobs.
	 */
	public function __construct(
		IngestionBatchProvider $batch_provider,
		SyncJobFactory $sync_job_factory
	) {
		$this->batch_provider   = $batch_provider;
		$this->sync_job_factory = $sync_job_factory;
	}

	/**
	 * Initialize the ingestion manager by registering hooks and scheduling recurring sync.
	 *
	 * @return void
	 */
	public function init() {
		$this->register_hooks();
		$this->schedule_recurring_sync();
	}

	/**
	 * Register the necessary hooks for the ingestion process.
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Main sync action.
		add_action( 'ppcp_agentic_sync_batch', array( $this, 'process_next_batch' ) );

		// Handle re-sync on product update.
		add_action( 'woocommerce_update_product', array( $this, 'mark_product_for_sync' ) );
		add_action( 'woocommerce_product_set_stock', array( $this, 'mark_product_for_sync' ) );
	}

	/**
	 * Schedule the recurring sync action.
	 *
	 * @return void
	 */
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
	 * Process the next batch of products for sync.
	 *
	 * @throws \Exception When an error occurs during sync.
	 * @wp-hook ppcp_agentic_sync_batch
	 * @return void
	 */
	public function process_next_batch(): void {
		// Get products needing sync using WooCommerce APIs.
		$product_ids = $this->batch_provider->get_batch( $this->batch_size );

		if ( empty( $product_ids ) ) {
			return; // Nothing to sync.
		}
		$sync_job = $this->sync_job_factory->create_job( $product_ids );
		$sync_job->execute();
	}

	/**
	 * Mark a product for sync when it's updated.
	 *
	 * @wp-hook woocommerce_update_product
	 * @param int $product_id The ID of the product being updated.
	 * @return void
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
