<?php

/**
 * The compatibility module services.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat;

use WooCommerce\PayPalCommerce\Compat\Assets\CompatAssets;
use WooCommerce\PayPalCommerce\Compat\Settings\GeneralSettingsMapHelper;
use WooCommerce\PayPalCommerce\Compat\Settings\PaymentMethodSettingsMapHelper;
use WooCommerce\PayPalCommerce\Compat\Settings\SettingsMap;
use WooCommerce\PayPalCommerce\Compat\Settings\SettingsMapHelper;
use WooCommerce\PayPalCommerce\Compat\Settings\SettingsTabMapHelper;
use WooCommerce\PayPalCommerce\Compat\Settings\StylingSettingsMapHelper;
use WooCommerce\PayPalCommerce\Compat\Settings\SubscriptionSettingsMapHelper;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array(
    'compat.ppec.mock-gateway' => static function ($container) {
        $settings = $container->get('wcgateway.settings');
        $title = $settings->has('title') ? $settings->get('title') : __('PayPal', 'woocommerce-paypal-payments');
        $title = sprintf(
            /* Translators: placeholder is the gateway name. */
            __('%s (Legacy)', 'woocommerce-paypal-payments'),
            $title
        );
        return new \WooCommerce\PayPalCommerce\Compat\PPEC\MockGateway($title);
    },
    'compat.ppec.subscriptions-handler' => static function (ContainerInterface $container) {
        $ppcp_renewal_handler = $container->get('wc-subscriptions.renewal-handler');
        $gateway = $container->get('compat.ppec.mock-gateway');
        return new \WooCommerce\PayPalCommerce\Compat\PPEC\SubscriptionsHandler($ppcp_renewal_handler, $gateway);
    },
    'compat.ppec.settings_importer' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Compat\PPEC\SettingsImporter {
        $settings = $container->get('wcgateway.settings');
        return new \WooCommerce\PayPalCommerce\Compat\PPEC\SettingsImporter($settings);
    },
    'compat.plugin-script-names' => static function (ContainerInterface $container): array {
        return array('ppcp-smart-button', 'ppcp-oxxo', 'ppcp-pay-upon-invoice', 'ppcp-vaulting-myaccount-payments', 'ppcp-gateway-settings', 'ppcp-webhooks-status-page', 'ppcp-tracking', 'ppcp-fraudnet', 'ppcp-tracking-compat', 'ppcp-clear-db');
    },
    'compat.plugin-script-file-names' => static function (ContainerInterface $container): array {
        return array('button.js', 'gateway-settings.js', 'status-page.js', 'order-edit-page.js', 'fraudnet.js', 'tracking-compat.js', 'ppcp-clear-db.js');
    },
    'compat.gzd.is_supported_plugin_version_active' => function (): bool {
        return function_exists('wc_gzd_get_shipments_by_order');
        // 3.0+
    },
    'compat.wc_shipment_tracking.is_supported_plugin_version_active' => function (): bool {
        return class_exists('WC_Shipment_Tracking');
    },
    'compat.ywot.is_supported_plugin_version_active' => function (): bool {
        return function_exists('yith_ywot_init');
    },
    'compat.dhl.is_supported_plugin_version_active' => function (): bool {
        return function_exists('PR_DHL');
    },
    'compat.shipstation.is_supported_plugin_version_active' => function (): bool {
        return function_exists('woocommerce_shipstation_init');
    },
    'compat.wc_shipping_tax.is_supported_plugin_version_active' => function (): bool {
        return class_exists('WC_Connect_Loader');
    },
    'compat.nyp.is_supported_plugin_version_active' => function (): bool {
        return function_exists('wc_nyp_init');
    },
    'compat.wc_bookings.is_supported_plugin_version_active' => function (): bool {
        return class_exists('WC_Bookings');
    },
    'compat.module.url' => static function (ContainerInterface $container): string {
        return plugins_url('/modules/ppcp-compat/', $container->get('ppcp.path-to-plugin-main-file'));
    },
    'compat.assets' => function (ContainerInterface $container): CompatAssets {
        return new CompatAssets($container->get('compat.module.url'), $container->get('ppcp.asset-version'), $container->get('compat.gzd.is_supported_plugin_version_active'), $container->get('compat.wc_shipment_tracking.is_supported_plugin_version_active'), $container->get('compat.wc_shipping_tax.is_supported_plugin_version_active'), $container->get('api.bearer'));
    },
    /**
     * Configuration for the new/old settings map.
     *
     * @returns SettingsMap[]
     */
    'compat.setting.new-to-old-map' => static function (ContainerInterface $container): array {
        $are_new_settings_enabled = $container->get('wcgateway.settings.admin-settings-enabled');
        if (!$are_new_settings_enabled) {
            return array();
        }
        $styling_settings_map_helper = $container->get('compat.settings.styling_map_helper');
        assert($styling_settings_map_helper instanceof StylingSettingsMapHelper);
        $settings_tab_map_helper = $container->get('compat.settings.settings_tab_map_helper');
        assert($settings_tab_map_helper instanceof SettingsTabMapHelper);
        $subscription_map_helper = $container->get('compat.settings.subscription_map_helper');
        assert($subscription_map_helper instanceof SubscriptionSettingsMapHelper);
        $general_map_helper = $container->get('compat.settings.general_map_helper');
        assert($general_map_helper instanceof GeneralSettingsMapHelper);
        $payment_methods_map_helper = $container->get('compat.settings.payment_methods_map_helper');
        assert($payment_methods_map_helper instanceof PaymentMethodSettingsMapHelper);
        return array(
            new SettingsMap($container->get('settings.data.general'), $general_map_helper->map()),
            new SettingsMap($container->get('settings.data.settings'), $settings_tab_map_helper->map()),
            new SettingsMap(
                $container->get('settings.data.styling'),
                /**
                 * The `StylingSettings` class stores settings as `LocationStylingDTO` objects.
                 * This method creates a mapping from old setting keys to the corresponding style names.
                 *
                 * Example:
                 * 'button_product_layout' => 'layout'
                 *
                 * This mapping will allow to retrieve the correct style value
                 * from a `LocationStylingDTO` object by dynamically accessing its properties.
                 */
                $styling_settings_map_helper->map()
            ),
            new SettingsMap($container->get('settings.data.settings'), $subscription_map_helper->map()),
            /**
             * We need to pass the PaymentSettings model instance to use it in some helpers.
             * Once the new settings module is permanently enabled,
             * this model can be passed as a dependency to the appropriate helper classes.
             * For now, we must pass it this way to avoid errors when the new settings module is disabled.
             */
            new SettingsMap($container->get('settings.data.payment'), array()),
            new SettingsMap($container->get('settings.data.payment'), $payment_methods_map_helper->map()),
        );
    },
    'compat.settings.settings_map_helper' => static function (ContainerInterface $container): SettingsMapHelper {
        return new SettingsMapHelper($container->get('compat.setting.new-to-old-map'), $container->get('compat.settings.styling_map_helper'), $container->get('compat.settings.settings_tab_map_helper'), $container->get('compat.settings.subscription_map_helper'), $container->get('compat.settings.general_map_helper'), $container->get('compat.settings.payment_methods_map_helper'), $container->get('wcgateway.settings.admin-settings-enabled'));
    },
    'compat.settings.styling_map_helper' => static function (ContainerInterface $container): StylingSettingsMapHelper {
        $context_provider = static function () use ($container): string {
            $context = $container->get('button.helper.context');
            return $context->context();
        };
        return new StylingSettingsMapHelper($context_provider);
    },
    'compat.settings.settings_tab_map_helper' => static function (): SettingsTabMapHelper {
        return new SettingsTabMapHelper();
    },
    'compat.settings.subscription_map_helper' => static function (ContainerInterface $container): SubscriptionSettingsMapHelper {
        return new SubscriptionSettingsMapHelper($container->get('wc-subscriptions.helper'));
    },
    'compat.settings.general_map_helper' => static function (): GeneralSettingsMapHelper {
        return new GeneralSettingsMapHelper();
    },
    'compat.settings.payment_methods_map_helper' => static function (): PaymentMethodSettingsMapHelper {
        return new PaymentMethodSettingsMapHelper();
    },
);
