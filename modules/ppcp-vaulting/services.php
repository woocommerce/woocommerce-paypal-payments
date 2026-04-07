<?php

/**
 * The vaulting module services.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vaulting;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('vaulting.payment-token-factory' => function (): \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenFactory {
    return new \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenFactory();
}, 'vaulting.wc-payment-tokens' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens {
    return new \WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens($container->get('vaulting.payment-token-factory'), $container->get('api.endpoint.payment-tokens'), $container->get('woocommerce.logger.woocommerce'));
});
