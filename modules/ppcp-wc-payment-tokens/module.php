<?php

/**
 * The vaulting module.
 *
 * @package WooCommerce\PayPalCommerce\WcPaymentTokens
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcPaymentTokens;

return static function (): \WooCommerce\PayPalCommerce\WcPaymentTokens\WcPaymentTokensModule {
    return new \WooCommerce\PayPalCommerce\WcPaymentTokens\WcPaymentTokensModule();
};
