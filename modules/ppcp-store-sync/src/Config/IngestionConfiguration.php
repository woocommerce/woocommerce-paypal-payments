<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Config;

use Automattic\WooCommerce\Enums\ProductType;
use Automattic\WooCommerce\Enums\ProductStatus;
/**
 * Centralized configuration for the product ingestion.
 *
 * Todo: Should we add a filter to all getters to allow modifying the values?
 */
class IngestionConfiguration
{
    private const CATALOG_LIFESPAN_IN_DAYS = 5;
    private const SYNC_INTERVAL_IN_SECONDS = 15 * MINUTE_IN_SECONDS;
    private const SYNC_BATCH_SIZE = 50;
    private const SUPPORTED_PRODUCT_TYPES = array(ProductType::SIMPLE, ProductType::VARIABLE);
    /**
     * How many days are products kept in PayPal's product catalog before they
     * consider the item to be stale?
     *
     * We need to sync every product before they become stale, otherwise they are
     * removed from the product catalog.
     */
    public function get_product_lifespan_in_days(): int
    {
        return self::CATALOG_LIFESPAN_IN_DAYS;
    }
    /**
     * The unix timestamp of the oldest product that is still considered "fresh" but
     * which is about to get stale within the next 5 sync cycles.
     */
    public function get_expired_product_timestamp(): int
    {
        $lifespan = 24 * HOUR_IN_SECONDS * $this->get_product_lifespan_in_days();
        $sync_buffer = 5 * self::SYNC_INTERVAL_IN_SECONDS;
        return time() - $lifespan + $sync_buffer;
    }
    /**
     * Interval of the recurring sync process.
     */
    public function get_sync_interval_in_seconds(): int
    {
        return self::SYNC_INTERVAL_IN_SECONDS;
    }
    /**
     * How many products are included in one sync batch?
     */
    public function get_sync_batch_size(): int
    {
        return self::SYNC_BATCH_SIZE;
    }
    /**
     * Single source of truth providing all filters to identify products which are compatible
     * with store-sync.
     *
     * @return array
     */
    public function get_valid_product_filters(): array
    {
        return array('status' => ProductStatus::PUBLISH, 'type' => self::SUPPORTED_PRODUCT_TYPES, 'downloadable' => \false);
    }
}
