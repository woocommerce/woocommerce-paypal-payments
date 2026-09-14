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
    private \WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductFilter $product_filter;
    public function __construct(IngestionConfiguration $configuration, \WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductFilter $product_filter)
    {
        $this->configuration = $configuration;
        $this->product_filter = $product_filter;
    }
    /**
     * Get a batch of products that need to be synced.
     *
     * The batch prioritizes products in this order:
     * 1. Products that have never been synced
     * 2. Products that have been updated since last sync
     * 3. Products that haven't been synced in the configured stale timeout period
     *
     * Candidates are filtered live through {@see ProductFilter}: ineligible products
     * are parked (so subsequent queries skip them) and the batch is topped up until
     * it is full or the candidate pool is exhausted.
     *
     * @return array An array of product IDs that need to be synced.
     */
    public function get_batch(): array
    {
        $stale_date = $this->configuration->get_expired_product_timestamp();
        // Define meta queries for different product states.
        $meta_fresh = array('key' => \WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductFilter::META_KEY, 'compare' => 'NOT EXISTS');
        $meta_stale = array('key' => \WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductFilter::META_KEY, 'value' => $stale_date, 'compare' => '<', 'type' => 'NUMERIC');
        // Fresh: Products have local changes that were not synced yet.
        $fresh = $this->collect_eligible($meta_fresh);
        // Stale: Products in the catalog that were not re-synced for a long time.
        $stale = $this->collect_eligible($meta_stale, $fresh, \true);
        return array_merge($fresh, $stale);
    }
    /**
     * Collects eligible product IDs for a single freshness clause.
     *
     * Repeatedly queries candidates and passes each live through {@see ProductFilter}:
     * eligible ones are collected, ineligible ones are parked (which drops them out of
     * the next query in this same run and of all future runs). Stops when the batch is
     * full or the candidate pool is exhausted.
     *
     * @param array $meta_query    The freshness clause for this pass.
     * @param array $current_batch Product IDs already collected by an earlier pass.
     * @param bool  $order_by_meta Whether to order results by meta-value (stale pass).
     * @return array The product IDs newly collected by this pass.
     */
    private function collect_eligible(array $meta_query, array $current_batch = array(), bool $order_by_meta = \false): array
    {
        $batch_size = $this->configuration->get_sync_batch_size();
        $items_in_batch = count($current_batch);
        $remaining_items = $batch_size - $items_in_batch;
        $collected = array();
        $seen = array();
        while ($remaining_items > 0) {
            $candidates = $this->query_candidates($meta_query, array_merge($current_batch, $seen), $remaining_items, $order_by_meta);
            if (empty($candidates)) {
                break;
                // Pool exhausted for this clause.
            }
            foreach ($candidates as $product_id) {
                // Excluding every seen candidate (not just collected ones)
                // guarantees the pool shrinks each pass, so the loop always
                // terminates even when a product cannot be loaded or parked.
                $seen[] = (int) $product_id;
                $product = wc_get_product($product_id);
                if (!$product) {
                    continue;
                }
                if (!$this->product_filter->passes_exclusion_filter($product)) {
                    $this->product_filter->mark_processed($product, 'Excluded by filter');
                    continue;
                }
                $collected[] = (int) $product_id;
                --$remaining_items;
            }
        }
        return $collected;
    }
    /**
     * Queries a page of candidate product IDs, constrained to eligible+not-parked.
     *
     * @param array $meta_query    The freshness clause.
     * @param array $exclude       Product IDs already collected (skip them).
     * @param int   $limit         Maximum number of candidates to return.
     * @param bool  $order_by_meta Whether to order by the freshness meta-value.
     * @return array Array of product IDs.
     */
    private function query_candidates(array $meta_query, array $exclude, int $limit, bool $order_by_meta): array
    {
        // phpcs:disable WordPress.DB.SlowDBQuery -- intentionally using the meta_query here.
        $args = array('limit' => $limit, 'return' => 'ids', 'exclude' => $exclude);
        // Add ordering for stale products (oldest first).
        if ($order_by_meta && isset($meta_query['key'])) {
            $args['orderby'] = 'meta_value_num';
            $args['order'] = 'ASC';
            $args['meta_key'] = $meta_query['key'];
        }
        // Constrain to the coarse eligibility criteria (status/type/downloadable).
        $args = array_merge($args, $this->product_filter->query_filters());
        // wc_get_products() silently discards a raw 'meta_query' argument
        // (WC_Data_Store_WP::get_wp_query_args() skips it), so the freshness clause
        // is injected into the underlying WP_Query args through WooCommerce's own
        // query filter instead.
        $inject_meta_query = static function (array $wp_query_args) use ($meta_query): array {
            $wp_query_args['meta_query'][] = $meta_query;
            return $wp_query_args;
        };
        add_filter('woocommerce_product_data_store_cpt_get_products_query', $inject_meta_query);
        try {
            $products = wc_get_products($args);
        } finally {
            remove_filter('woocommerce_product_data_store_cpt_get_products_query', $inject_meta_query);
        }
        // phpcs:enable WordPress.DB.SlowDBQuery
        assert(is_array($products));
        return $products;
    }
}
