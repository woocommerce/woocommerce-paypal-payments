<?php

/**
 * The module.
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcSubscriptions;

return static function (): \WooCommerce\PayPalCommerce\WcSubscriptions\WcSubscriptionsModule {
    return new \WooCommerce\PayPalCommerce\WcSubscriptions\WcSubscriptionsModule();
};
