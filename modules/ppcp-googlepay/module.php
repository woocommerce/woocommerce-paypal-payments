<?php

/**
 * The Googlepay module.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Googlepay;

return static function (): \WooCommerce\PayPalCommerce\Googlepay\GooglepayModule {
    return new \WooCommerce\PayPalCommerce\Googlepay\GooglepayModule();
};
