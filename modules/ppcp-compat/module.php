<?php

/**
 * The compatibility module.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat;

return static function (): \WooCommerce\PayPalCommerce\Compat\CompatModule {
    return new \WooCommerce\PayPalCommerce\Compat\CompatModule();
};
