<?php

/**
 * The Pay Later configurator module services.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterConfigurator
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayLaterConfigurator;

use WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint\SaveConfig;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint\GetConfig;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Factory\ConfigFactory;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
return array('paylater-configurator.url' => static function (ContainerInterface $container): string {
    return plugins_url('/modules/ppcp-paylater-configurator', $container->get('ppcp.path-to-plugin-main-file'));
}, 'paylater-configurator.factory.config' => static function (ContainerInterface $container): ConfigFactory {
    return new ConfigFactory();
}, 'paylater-configurator.endpoint.save-config' => static function (ContainerInterface $container): SaveConfig {
    return new SaveConfig($container->get('wcgateway.settings'), $container->get('button.request-data'), $container->get('woocommerce.logger.woocommerce'));
}, 'paylater-configurator.endpoint.get-config' => static function (ContainerInterface $container): GetConfig {
    return new GetConfig($container->get('wcgateway.settings'), $container->get('woocommerce.logger.woocommerce'));
}, 'paylater-configurator.is-available' => static function (ContainerInterface $container): bool {
    // Test, if Pay-Later is available; depends on the shop country and Vaulting status.
    $messages_apply = $container->get('button.helper.messages-apply');
    assert($messages_apply instanceof MessagesApply);
    $settings = $container->get('wcgateway.settings');
    assert($settings instanceof Settings);
    $dcc_product_status = $container->get('wcgateway.helper.dcc-product-status');
    assert($dcc_product_status instanceof DCCProductStatus);
    $vault_enabled = $settings->has('vault_enabled') && $settings->get('vault_enabled');
    // Pay Later Messaging is available if vaulting is not enabled, the shop country is supported, and is eligible for ACDC.
    return !$vault_enabled && $messages_apply->for_country() && $dcc_product_status->is_active();
}, 'paylater-configurator.messaging-locations' => static function (ContainerInterface $container): array {
    // Get an array of locations that display the Pay-Later message.
    $settings = $container->get('wcgateway.settings');
    assert($settings instanceof Settings);
    $is_enabled = $settings->has('pay_later_messaging_enabled') && $settings->get('pay_later_messaging_enabled');
    if (!$is_enabled) {
        return array();
    }
    $selected_locations = $settings->has('pay_later_messaging_locations') ? $settings->get('pay_later_messaging_locations') : array();
    if (is_array($selected_locations)) {
        return $selected_locations;
    }
    return array();
});
