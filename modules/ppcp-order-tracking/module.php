<?php

/**
 * The order tracking module.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderTracking;

return static function (): \WooCommerce\PayPalCommerce\OrderTracking\OrderTrackingModule {
    return new \WooCommerce\PayPalCommerce\OrderTracking\OrderTrackingModule();
};
