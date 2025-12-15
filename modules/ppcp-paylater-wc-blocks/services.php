<?php

/**
 * The Pay Later WooCommerce Blocks module services.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterWCBlocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('paylater-wc-blocks.url' => static function (ContainerInterface $container): string {
    return plugins_url('/modules/ppcp-paylater-wc-blocks/', $container->get('ppcp.path-to-plugin-main-file'));
}, 'paylater-wc-blocks.cart-renderer' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksRenderer {
    $settings = $container->get('wcgateway.settings');
    return new \WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksRenderer(array('placement' => 'cart', 'layout' => $settings->has('pay_later_cart_message_layout') ? $settings->get('pay_later_cart_message_layout') : '', 'position' => $settings->has('pay_later_cart_message_position') ? $settings->get('pay_later_cart_message_position') : '', 'logo' => $settings->has('pay_later_cart_message_logo') ? $settings->get('pay_later_cart_message_logo') : '', 'text_size' => $settings->has('pay_later_cart_message_text_size') ? $settings->get('pay_later_cart_message_text_size') : '', 'color' => $settings->has('pay_later_cart_message_color') ? $settings->get('pay_later_cart_message_color') : '', 'flex_color' => $settings->has('pay_later_cart_message_flex_color') ? $settings->get('pay_later_cart_message_flex_color') : '', 'flex_ratio' => $settings->has('pay_later_cart_message_flex_ratio') ? $settings->get('pay_later_cart_message_flex_ratio') : ''));
}, 'paylater-wc-blocks.checkout-renderer' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksRenderer {
    $settings = $container->get('wcgateway.settings');
    return new \WooCommerce\PayPalCommerce\PayLaterWCBlocks\PayLaterWCBlocksRenderer(array('payment', 'layout' => $settings->has('pay_later_checkout_message_layout') ? $settings->get('pay_later_checkout_message_layout') : '', 'position' => $settings->has('pay_later_checkout_message_position') ? $settings->get('pay_later_checkout_message_position') : '', 'logo' => $settings->has('pay_later_checkout_message_logo') ? $settings->get('pay_later_checkout_message_logo') : '', 'text_size' => $settings->has('pay_later_checkout_message_text_size') ? $settings->get('pay_later_checkout_message_text_size') : '', 'color' => $settings->has('pay_later_checkout_message_color') ? $settings->get('pay_later_checkout_message_color') : '', 'flex_color' => $settings->has('pay_later_checkout_message_flex_color') ? $settings->get('pay_later_checkout_message_flex_color') : '', 'flex_ratio' => $settings->has('pay_later_checkout_message_flex_ratio') ? $settings->get('pay_later_checkout_message_flex_ratio') : ''));
});
