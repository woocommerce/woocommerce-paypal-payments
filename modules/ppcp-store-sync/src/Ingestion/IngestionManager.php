<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use RuntimeException;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\StoreSync\Config\AgenticWebhookConfiguration;
use WooCommerce\PayPalCommerce\StoreSync\Config\IngestionConfiguration;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;
use WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadataProvider;
use function as_next_scheduled_action;
use function as_schedule_recurring_action;
/**
 * Manages the ingestion process for agentic commerce.
 * This class handles scheduling sync jobs and marking products for sync.
 */
class IngestionManager
{
    private IngestionConfiguration $configuration;
    private \WooCommerce\PayPalCommerce\StoreSync\Ingestion\IngestionBatchProvider $batch_provider;
    private AgenticWebhookConfiguration $webhook_urls;
    private MerchantMetadataProvider $metadata_provider;
    private LoggerInterface $logger;
    private ProductManager $product_manager;
    public function __construct(IngestionConfiguration $configuration, \WooCommerce\PayPalCommerce\StoreSync\Ingestion\IngestionBatchProvider $batch_provider, AgenticWebhookConfiguration $webhook_urls, MerchantMetadataProvider $metadata_provider, LoggerInterface $logger, ProductManager $product_manager)
    {
        $this->configuration = $configuration;
        $this->batch_provider = $batch_provider;
        $this->webhook_urls = $webhook_urls;
        $this->metadata_provider = $metadata_provider;
        $this->logger = $logger;
        $this->product_manager = $product_manager;
    }
    /**
     * Initialize the ingestion manager by registering hooks and scheduling recurring sync.
     */
    public function init(): void
    {
        $this->register_hooks();
        $this->schedule_recurring_sync();
    }
    /**
     * Register the necessary hooks for the ingestion process.
     */
    private function register_hooks(): void
    {
        // Main sync action.
        add_action('ppcp_agentic_sync_batch', array($this, 'process_next_batch'));
        // Handle re-sync on product update.
        add_action('woocommerce_update_product', array($this, 'mark_product_for_sync'));
        add_action('woocommerce_product_set_stock', array($this, 'mark_product_for_sync'));
    }
    /**
     * Schedule the recurring sync action.
     */
    private function schedule_recurring_sync(): void
    {
        if (as_next_scheduled_action('ppcp_agentic_sync_batch')) {
            return;
        }
        as_schedule_recurring_action(time(), $this->configuration->get_sync_interval_in_seconds(), 'ppcp_agentic_sync_batch', array(), 'ppcp_agentic_sync');
    }
    /**
     * Unschedules the recurring action when the store is unregistered.
     *
     * @return void
     */
    public function clear_recurring_schedule(): void
    {
        if (!as_next_scheduled_action('ppcp_agentic_sync_batch')) {
            return;
        }
        as_unschedule_action('ppcp_agentic_sync_batch', array(), 'ppcp_agentic_sync');
    }
    /**
     * Process the next batch of products for sync.
     *
     * @throws RuntimeException When an error occurs during sync, handled by Action Scheduler.
     * @wp-hook ppcp_agentic_sync_batch
     */
    public function process_next_batch(): void
    {
        // Get products needing sync using WooCommerce APIs.
        $product_ids = $this->batch_provider->get_batch();
        if (empty($product_ids)) {
            return;
            // Nothing to sync.
        }
        $sync_job = $this->create_new_sync_job($product_ids);
        $sync_job->execute();
    }
    /**
     * Mark a product for sync when it's updated.
     *
     * @wp-hook woocommerce_update_product
     * @wp-hook woocommerce_product_set_stock
     * @param mixed $product_id The ID of the product being updated.
     */
    public function mark_product_for_sync($product_id): void
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        $product->delete_meta_data('_ppcp_agentic_last_sync');
        $product->save_meta_data();
    }
    /**
     * Creates a new SyncJob instance for the given product IDs.
     *
     * This method instantiates a SyncJob with the factory's configured API endpoint
     * and logger, along with the specified product IDs to be synchronized.
     */
    private function create_new_sync_job(array $product_ids): \WooCommerce\PayPalCommerce\StoreSync\Ingestion\SyncJob
    {
        $metadata = $this->metadata_provider->get_metadata();
        return new \WooCommerce\PayPalCommerce\StoreSync\Ingestion\SyncJob($this->webhook_urls->get_product_ingestion_url(), $metadata->store_url, $product_ids, $this->logger, $this->product_manager);
    }
}
