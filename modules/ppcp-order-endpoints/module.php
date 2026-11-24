<?php

/**
 * The order endpoints module.
 *
 * @package WooCommerce\PayPalCommerce\OrderEndpoints
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderEndpoints;

return static function (): \WooCommerce\PayPalCommerce\OrderEndpoints\OrderEndpointsModule {
    return new \WooCommerce\PayPalCommerce\OrderEndpoints\OrderEndpointsModule();
};
