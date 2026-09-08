<?php

/**
 * The Pay Later WooCommerce Blocks module services.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterWCBlocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\Settings\Data\PayLaterMessagingSettings;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('paylater-wc-blocks.asset_getter' => static function (ContainerInterface $container): AssetGetter {
    $factory = $container->get('assets.asset_getter_factory');
    assert($factory instanceof AssetGetterFactory);
    return $factory->for_module('ppcp-paylater-wc-blocks');
}, 'paylater-wc-blocks.cart-renderer' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksRenderer {
    $paylater_settings = $container->get('settings.data.paylater-messaging-settings');
    assert($paylater_settings instanceof PayLaterMessagingSettings);
    $cart = $paylater_settings->get_cart();
    return new \WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksRenderer(array('placement' => 'cart', 'layout' => $cart->layout, 'position' => $cart->logo_position, 'logo' => $cart->logo_type, 'text_size' => $cart->text_size, 'color' => $cart->text_color, 'flex_color' => $cart->flex_color, 'flex_ratio' => $cart->flex_ratio));
}, 'paylater-wc-blocks.checkout-renderer' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksRenderer {
    $paylater_settings = $container->get('settings.data.paylater-messaging-settings');
    assert($paylater_settings instanceof PayLaterMessagingSettings);
    $checkout = $paylater_settings->get_checkout();
    return new \WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksRenderer(array('placement' => 'payment', 'layout' => $checkout->layout, 'position' => $checkout->logo_position, 'logo' => $checkout->logo_type, 'text_size' => $checkout->text_size, 'color' => $checkout->text_color, 'flex_color' => $checkout->flex_color, 'flex_ratio' => $checkout->flex_ratio));
});
