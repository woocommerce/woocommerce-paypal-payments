<?php

/**
 * The local alternative payment methods module.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

return static function (): \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\LocalAlternativePaymentMethodsModule {
    return new \WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\LocalAlternativePaymentMethodsModule();
};
