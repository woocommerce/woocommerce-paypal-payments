<?php

/**
 * The agentic commerce module.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync;

return static function (): \WooCommerce\PayPalCommerce\StoreSync\StoreSyncModule {
    return new \WooCommerce\PayPalCommerce\StoreSync\StoreSyncModule();
};
