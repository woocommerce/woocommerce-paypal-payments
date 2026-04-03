<?php

/**
 * The vaulting module services.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vaulting;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('vaulting.payment-token-factory' => function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenFactory {
    return new \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenFactory();
}, 'vaulting.payment-token-helper' => function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenHelper {
    return new \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenHelper();
}, 'vaulting.wc-payment-tokens' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens {
    return new \WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens($container->get('vaulting.payment-token-helper'), $container->get('vaulting.payment-token-factory'), $container->get('api.endpoint.payment-tokens'), $container->get('woocommerce.logger.woocommerce'));
});
