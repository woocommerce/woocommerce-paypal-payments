<?php

/**
 * The SDK v6 module services.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\SdkV6\Assets\SdkV6Manager;
use WooCommerce\PayPalCommerce\SdkV6\Blocks\V6PaymentMethod;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ButtonStyleMapper;
use WooCommerce\PayPalCommerce\SdkV6\Helper\GooglePayConfig;
use WooCommerce\PayPalCommerce\SdkV6\Helper\RateLimiter;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
return array('sdk-v6.asset-getter' => static function (ContainerInterface $container): AssetGetter {
    $factory = $container->get('assets.asset_getter_factory');
    assert($factory instanceof AssetGetterFactory);
    return $factory->for_module('ppcp-sdk-v6');
}, 'sdk-v6.button-style-mapper' => static function (ContainerInterface $container): ButtonStyleMapper {
    return new ButtonStyleMapper($container->get('settings.settings-provider'));
}, 'sdk-v6.google-pay-config' => static function (ContainerInterface $container): GooglePayConfig {
    $eligibility_check = static function () use ($container): bool {
        // Google Pay module is not loaded, this wallet is unavailable.
        if (!$container->has('googlepay.eligibility.check') || !$container->has('googlepay.available')) {
            return \false;
        }
        $is_eligible = $container->get('googlepay.eligibility.check');
        return $is_eligible() && $container->get('googlepay.available');
    };
    return new GooglePayConfig($container->get('settings.settings-provider'), $container->get('wc-subscriptions.helper'), $eligibility_check);
}, 'sdk-v6.manager' => static function (ContainerInterface $container): SdkV6Manager {
    return new SdkV6Manager(
        $container->get('sdk-v6.asset-getter'),
        $container->get('ppcp.asset-version'),
        $container->get('settings.environment'),
        $container->get('sdk-v6.button-style-mapper'),
        $container->get('order-endpoints.handle-shipping-in-paypal'),
        $container->get('wcgateway.settings.status'),
        $container->get('button.helper.context'),
        $container->get('session.handler'),
        $container->get('session.cancellation.view'),
        // Computed here rather than reusing blocks.settings.final_review_enabled
        // so this module does not depend on the ppcp-blocks module it replaces.
        !$container->get('settings.settings-provider')->enable_pay_now(),
        $container->get('settings.settings-provider')->save_paypal_and_venmo(),
        $container->get('wcgateway.configuration.card-configuration'),
        $container->get('sdk-v6.google-pay-config')
    );
}, 'sdk-v6.endpoint.client-token' => static function (ContainerInterface $container): ClientTokenEndpoint {
    return new ClientTokenEndpoint($container->get('order-endpoints.request-data'), $container->get('woocommerce.logger.woocommerce'), $container->get('api.sdk-client-token'), $container->get('sdk-v6.rate-limiter'));
}, 'sdk-v6.rate-limiter' => static function (ContainerInterface $container): RateLimiter {
    return new RateLimiter('ppcp_sdk_v6_rl_', 10, 60);
}, 'sdk-v6.blocks.payment-method' => static function (ContainerInterface $container): V6PaymentMethod {
    return new V6PaymentMethod($container->get('sdk-v6.manager'), $container->get('sdk-v6.asset-getter'), $container->get('ppcp.asset-version'), $container->get('wcgateway.paypal-gateway'));
});
