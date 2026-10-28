<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Config;

/**
 * Centralized configuration for the product ingestion.
 *
 * Owns scheduling and sizing knobs only. Eligibility (which products qualify) is
 * owned by {@see \WooCommerce\PayPalCommerce\StoreSync\Ingestion\ProductFilter}.
 */
class IngestionConfiguration
{
    private const CATALOG_LIFESPAN_IN_DAYS = 5;
    private const SYNC_INTERVAL_IN_SECONDS = 15 * MINUTE_IN_SECONDS;
    private const SYNC_BATCH_SIZE = 50;
    /**
     * The unix timestamp of the oldest product that is still considered "fresh" but
     * which is about to get stale within the next 5 sync cycles.
     *
     * Products are removed from PayPal's catalog once they become stale, so every
     * product must be re-synced before it crosses this threshold.
     */
    public function get_expired_product_timestamp(): int
    {
        $lifespan = 24 * HOUR_IN_SECONDS * self::CATALOG_LIFESPAN_IN_DAYS;
        $sync_buffer = 5 * self::SYNC_INTERVAL_IN_SECONDS;
        /**
         * Filters the unix timestamp beyond which a product is considered stale and
         * must be re-synced. Products last processed before this timestamp are
         * re-evaluated on the next run.
         *
         * @param int $timestamp The stale-threshold unix timestamp.
         */
        return (int) apply_filters('woocommerce_paypal_payments_store_sync_expired_product_timestamp', time() - $lifespan + $sync_buffer);
    }
    /**
     * Interval of the recurring sync process.
     */
    public function get_sync_interval_in_seconds(): int
    {
        /**
         * Filters the interval (in seconds) between recurring product-sync runs.
         *
         * @param int $interval The sync interval in seconds. Must be a positive integer.
         */
        return (int) apply_filters('woocommerce_paypal_payments_store_sync_interval', self::SYNC_INTERVAL_IN_SECONDS);
    }
    /**
     * How many products are included in one sync batch?
     */
    public function get_sync_batch_size(): int
    {
        /**
         * Filters the number of products included in one sync batch.
         *
         * @param int $batch_size The sync batch size. A value of 0 (or less) yields an
         *                        empty batch, which effectively pauses ingestion without
         *                        unscheduling the recurring sync.
         */
        return (int) apply_filters('woocommerce_paypal_payments_store_sync_batch_size', self::SYNC_BATCH_SIZE);
    }
}
