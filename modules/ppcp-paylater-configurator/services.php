<?php

/**
 * The Pay Later configurator module services.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterConfigurator
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayLaterConfigurator;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint\SaveConfig;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint\GetConfig;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Factory\ConfigFactory;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Settings\Data\PayLaterMessagingSettings;
return array('paylater-configurator.asset_getter' => static function (ContainerInterface $container): AssetGetter {
    $factory = $container->get('assets.asset_getter_factory');
    assert($factory instanceof AssetGetterFactory);
    return $factory->for_module('ppcp-paylater-configurator');
}, 'paylater-configurator.factory.config' => static function (ContainerInterface $container): ConfigFactory {
    return new ConfigFactory();
}, 'paylater-configurator.endpoint.save-config' => static function (ContainerInterface $container): SaveConfig {
    return new SaveConfig($container->get('settings.data.paylater-messaging-settings'), $container->get('button.request-data'), $container->get('woocommerce.logger.woocommerce'));
}, 'paylater-configurator.endpoint.get-config' => static function (ContainerInterface $container): GetConfig {
    return new GetConfig($container->get('settings.data.paylater-messaging-settings'), $container->get('woocommerce.logger.woocommerce'));
}, 'paylater-configurator.is-available' => static function (ContainerInterface $container): bool {
    $messages_apply = $container->get('button.helper.messages-apply');
    assert($messages_apply instanceof MessagesApply);
    $settings_provider = $container->get('settings.settings-provider');
    assert($settings_provider instanceof SettingsProvider);
    $dcc_product_status = $container->get('wcgateway.helper.dcc-product-status');
    assert($dcc_product_status instanceof DCCProductStatus);
    $vault_enabled = $settings_provider->save_paypal_and_venmo();
    return !$vault_enabled && $messages_apply->for_country();
}, 'paylater-configurator.messaging-locations' => static function (ContainerInterface $container): array {
    $settings_provider = $container->get('settings.settings-provider');
    assert($settings_provider instanceof SettingsProvider);
    if (!$settings_provider->paylater_enabled()) {
        return array();
    }
    return $settings_provider->pay_later_messaging_locations();
});
