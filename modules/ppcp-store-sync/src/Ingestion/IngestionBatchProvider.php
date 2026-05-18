<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Ingestion;

use WooCommerce\PayPalCommerce\StoreSync\Config\IngestionConfiguration;
/**
 * Provides a batch of WC_Product IDs eligible for
 * syncing with the agentic commerce product ingestion endpoint
 */
class IngestionBatchProvider
{
    private IngestionConfiguration $configuration;
    public function __construct(IngestionConfiguration $configuration)
    {
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
    public function get_batch(): array
    {
        $resync_timestamp = $this->configuration->get_expired_product_timestamp();
        $stale_date = gmdate('Y-m-d H:i:s', $resync_timestamp);
        // Define meta queries for different product states.
        $meta_fresh = array('key' => '_ppcp_agentic_last_sync', 'compare' => 'NOT EXISTS');
        $meta_stale = array('key' => '_ppcp_agentic_last_sync', 'value' => $stale_date, 'compare' => '<', 'type' => 'DATETIME');
        // Fresh: Products have local changes that were not synced yet.
        $fresh = $this->get_products($meta_fresh);
        // Stale: Products in the catalog that were not re-synced for a long time.
        $stale = $this->get_products($meta_stale, $fresh, \true);
        return array_merge($fresh, $stale);
    }
    /**
     * Get products matching the given meta query criteria.
     *
     * @param array $meta_query    The meta query criteria.
     * @param array $current_batch Product IDs that are already in the batch.
     * @param bool  $order_by_meta Whether to order results by meta-value (for stale products).
     * @return array Array of product IDs.
     */
    private function get_products(array $meta_query, array $current_batch = array(), bool $order_by_meta = \false): array
    {
        $batch_size = $this->configuration->get_sync_batch_size();
        $items_in_batch = count($current_batch);
        $remaining_items = $batch_size - $items_in_batch;
        if ($remaining_items <= 0) {
            return array();
        }
        // phpcs:disable WordPress.DB.SlowDBQuery -- intentionally using the meta_query here.
        $args = array_merge($this->configuration->get_valid_product_filters(), array('limit' => $remaining_items, 'return' => 'ids', 'meta_query' => array($meta_query), 'exclude' => $current_batch));
        // Add ordering for stale products (oldest first).
        if ($order_by_meta && isset($meta_query['key'])) {
            $args['orderby'] = 'meta_value';
            $args['order'] = 'ASC';
            $args['meta_key'] = $meta_query['key'];
        }
        $products = wc_get_products($args);
        // phpcs:enable WordPress.DB.SlowDBQuery
        assert(is_array($products));
        return $products;
    }
}
