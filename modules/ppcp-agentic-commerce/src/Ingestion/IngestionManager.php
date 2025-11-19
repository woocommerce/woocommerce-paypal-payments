<?php

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Ingestion;

use RuntimeException;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\AgenticCommerce\AgenticWebhookConfiguration;

use function as_next_scheduled_action;
use function as_schedule_recurring_action;

/**
 * Manages the ingestion process for agentic commerce.
 * This class handles scheduling sync jobs and marking products for sync.
 */
class IngestionManager {

	private const INTERVAL_IN_SECONDS = 15 * MINUTE_IN_SECONDS;

	/**
	 * The batch size for sync operations.
	 *
	 * @var int
	 */
	private int $batch_size = 50;
	private IngestionBatchProvider $batch_provider;
	private LoggerInterface $logger;
	private AgenticWebhookConfiguration $webhook_urls;

	public function __construct(
		IngestionBatchProvider $batch_provider,
		AgenticWebhookConfiguration $webhook_urls,
		LoggerInterface $logger
	) {

		$this->batch_provider = $batch_provider;
		$this->webhook_urls   = $webhook_urls;
		$this->logger         = $logger;
	}

	/**
	 * Initialize the ingestion manager by registering hooks and scheduling recurring sync.
	 */
	public function init(): void {
		$this->register_hooks();
		$this->schedule_recurring_sync();
	}

	/**
	 * Register the necessary hooks for the ingestion process.
	 */
	private function register_hooks(): void {
		// Main sync action.
		add_action( 'ppcp_agentic_sync_batch', array( $this, 'process_next_batch' ) );

		// Handle re-sync on product update.
		add_action( 'woocommerce_update_product', array( $this, 'mark_product_for_sync' ) );
		add_action( 'woocommerce_product_set_stock', array( $this, 'mark_product_for_sync' ) );
	}

	/**
	 * Schedule the recurring sync action.
	 */
	private function schedule_recurring_sync(): void {
		if ( as_next_scheduled_action( 'ppcp_agentic_sync_batch' ) ) {
			return;
		}

		as_schedule_recurring_action(
			time(),
			self::INTERVAL_IN_SECONDS,
			'ppcp_agentic_sync_batch',
			array(),
			'ppcp_agentic_sync'
		);
	}

	/**
	 * Process the next batch of products for sync.
	 *
	 * @throws RuntimeException When an error occurs during sync, handled by Action Scheduler.
	 * @wp-hook ppcp_agentic_sync_batch
	 */
	public function process_next_batch(): void {
		// Get products needing sync using WooCommerce APIs.
		$product_ids = $this->batch_provider->get_batch( $this->batch_size );

		if ( empty( $product_ids ) ) {
			return; // Nothing to sync.
		}

		$sync_job = $this->create_new_sync_job( $product_ids );
		$sync_job->execute();
	}

	/**
	 * Mark a product for sync when it's updated.
	 *
	 * @wp-hook woocommerce_update_product
	 * @wp-hook woocommerce_product_set_stock
	 * @param mixed $product_id The ID of the product being updated.
	 */
	public function mark_product_for_sync( $product_id ): void {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$product->update_meta_data( '_ppcp_agentic_needs_sync', '1' );
		$product->save_meta_data();
	}

	/**
	 * Creates a new SyncJob instance for the given product IDs.
	 *
	 * This method instantiates a SyncJob with the factory's configured API endpoint
	 * and logger, along with the specified product IDs to be synchronized.
	 */
	private function create_new_sync_job( array $product_ids ): SyncJob {
		return new SyncJob(
			$this->webhook_urls->get_product_ingestion_url(),
			$product_ids,
			$this->logger
		);
	}
}
