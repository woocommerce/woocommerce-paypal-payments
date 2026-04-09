<?php

/**
 * The vaulting module services.
 *
 * @package WooCommerce\PayPalCommerce\WcPaymentTokens
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcPaymentTokens;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('wc-payment-tokens.wc-payment-tokens' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\WcPaymentTokens\WooCommercePaymentTokens {
    return new \WooCommerce\PayPalCommerce\WcPaymentTokens\WooCommercePaymentTokens($container->get('api.endpoint.payment-tokens'), $container->get('woocommerce.logger.woocommerce'));
});
