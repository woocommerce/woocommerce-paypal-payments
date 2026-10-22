<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Traits;

use ReflectionProperty;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ProductManager;

/**
 * Empties the static product lookup cache of the store-sync ProductManager, so a
 * test never resolves a product that an earlier test already deleted.
 */
trait ClearsStoreSyncProductCache
{
    protected function clear_store_sync_product_cache(): void
    {
        $cache = new ReflectionProperty(ProductManager::class, 'product_cache');
        $cache->setAccessible(true);
        $cache->setValue(null, []);
    }
}
