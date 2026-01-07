<?php

/**
 * The vaulting module services.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vaulting;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('vaulting.repository.payment-token' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository {
    $factory = $container->get('api.factory.payment-token');
    $endpoint = $container->get('vault-v2.endpoint.payment-token');
    return new \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository($factory, $endpoint);
}, 'vaulting.customer-approval-listener' => function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Vaulting\CustomerApprovalListener {
    return new \WooCommerce\PayPalCommerce\Vaulting\CustomerApprovalListener($container->get('vault-v2.endpoint.payment-token'), $container->get('woocommerce.logger.woocommerce'));
}, 'vaulting.credit-card-handler' => function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Vaulting\VaultedCreditCardHandler {
    return new \WooCommerce\PayPalCommerce\Vaulting\VaultedCreditCardHandler($container->get('wc-subscriptions.helper'), $container->get('vaulting.repository.payment-token'), $container->get('api.factory.purchase-unit'), $container->get('api.factory.payer'), $container->get('api.factory.shipping-preference'), $container->get('api.endpoint.order'), $container->get('settings.environment'), $container->get('wcgateway.processor.authorized-payments'), $container->get('wcgateway.settings'));
}, 'vaulting.payment-token-factory' => function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenFactory {
    return new \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenFactory();
}, 'vaulting.payment-token-helper' => function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenHelper {
    return new \WooCommerce\PayPalCommerce\Vaulting\PaymentTokenHelper();
}, 'vaulting.payment-tokens-migration' => function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Vaulting\PaymentTokensMigration {
    return new \WooCommerce\PayPalCommerce\Vaulting\PaymentTokensMigration($container->get('vaulting.payment-token-factory'), $container->get('vaulting.repository.payment-token'), $container->get('vaulting.payment-token-helper'), $container->get('woocommerce.logger.woocommerce'));
}, 'vaulting.wc-payment-tokens' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens {
    return new \WooCommerce\PayPalCommerce\Vaulting\WooCommercePaymentTokens($container->get('vaulting.payment-token-helper'), $container->get('vaulting.payment-token-factory'), $container->get('api.endpoint.payment-tokens'), $container->get('woocommerce.logger.woocommerce'));
}, 'vaulting.vault-v3-enabled' => static function (ContainerInterface $container): bool {
    return $container->has('save-payment-methods.eligible') && $container->get('save-payment-methods.eligible');
});
