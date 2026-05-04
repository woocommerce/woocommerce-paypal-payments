<?php

/**
 * The compatibility module services.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\Compat\Assets\CompatAssets;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('compat.ppec.mock-gateway' => static function ($container) {
    $settings = $container->get('settings.settings-provider');
    assert($settings instanceof SettingsProvider);
    $title = sprintf(
        /* Translators: placeholder is the gateway name. */
        __('%s (Legacy)', 'woocommerce-paypal-payments'),
        $settings->paypal_gateway_title()
    );
    return new \WooCommerce\PayPalCommerce\Compat\PPEC\MockGateway($title);
}, 'compat.ppec.billing-agreement-converter' => static function (ContainerInterface $container) {
    return new \WooCommerce\PayPalCommerce\Compat\PPEC\BillingAgreementTokenConverter($container->get('api.endpoint.payment-method-tokens'), $container->get('api.repository.customer'), $container->get('woocommerce.logger.woocommerce'));
}, 'compat.ppec.subscriptions-handler' => static function (ContainerInterface $container) {
    return new \WooCommerce\PayPalCommerce\Compat\PPEC\SubscriptionsHandler($container->get('wc-subscriptions.renewal-handler'), $container->get('compat.ppec.mock-gateway'), $container->get('compat.ppec.billing-agreement-converter'), $container->get('woocommerce.logger.woocommerce'));
}, 'compat.plugin-script-names' => static function (ContainerInterface $container): array {
    return array('ppcp-smart-button', 'ppcp-oxxo', 'ppcp-pay-upon-invoice', 'ppcp-wc-payment-tokens-myaccount-payments', 'ppcp-gateway-settings', 'ppcp-webhooks-status-page', 'ppcp-tracking', 'ppcp-fraudnet', 'ppcp-tracking-compat');
}, 'compat.plugin-script-file-names' => static function (ContainerInterface $container): array {
    return array('button.js', 'gateway-settings.js', 'order-edit-page.js', 'fraudnet.js', 'tracking-compat.js');
}, 'compat.shiptastic.is_supported_plugin_version_active' => function (): bool {
    return function_exists('wc_stc_get_shipments');
}, 'compat.wc_shipment_tracking.is_supported_plugin_version_active' => function (): bool {
    return class_exists('WC_Shipment_Tracking');
}, 'compat.ywot.is_supported_plugin_version_active' => function (): bool {
    return function_exists('yith_ywot_init');
}, 'compat.dhl.is_supported_plugin_version_active' => function (): bool {
    return function_exists('PR_DHL');
}, 'compat.shipstation.is_supported_plugin_version_active' => function (): bool {
    return function_exists('woocommerce_shipstation_init');
}, 'compat.wc_shipping_tax.is_supported_plugin_version_active' => function (): bool {
    return class_exists('WC_Connect_Loader');
}, 'compat.nyp.is_supported_plugin_version_active' => function (): bool {
    return function_exists('wc_nyp_init');
}, 'compat.wc_bookings.is_supported_plugin_version_active' => function (): bool {
    return class_exists('WC_Bookings');
}, 'compat.asset_getter' => static function (ContainerInterface $container): AssetGetter {
    $factory = $container->get('assets.asset_getter_factory');
    assert($factory instanceof AssetGetterFactory);
    return $factory->for_module('ppcp-compat');
}, 'compat.assets' => function (ContainerInterface $container): CompatAssets {
    return new CompatAssets($container->get('compat.asset_getter'), $container->get('ppcp.asset-version'), $container->get('compat.shiptastic.is_supported_plugin_version_active'), $container->get('compat.wc_shipment_tracking.is_supported_plugin_version_active'), $container->get('compat.wc_shipping_tax.is_supported_plugin_version_active'), $container->get('api.bearer'));
});
