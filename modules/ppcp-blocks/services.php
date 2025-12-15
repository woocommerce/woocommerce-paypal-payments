<?php

/**
 * The blocks module services.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Blocks;

use WooCommerce\PayPalCommerce\Blocks\Endpoint\GetPayPalOrderFromSession;
use WooCommerce\PayPalCommerce\Blocks\Endpoint\UpdateShippingEndpoint;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WC_Cart;
return array('blocks.url' => static function (ContainerInterface $container): string {
    return plugins_url('/modules/ppcp-blocks/', $container->get('ppcp.path-to-plugin-main-file'));
}, 'blocks.method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Blocks\PayPalPaymentMethod {
    return new \WooCommerce\PayPalCommerce\Blocks\PayPalPaymentMethod($container->get('blocks.url'), $container->get('ppcp.asset-version'), function () use ($container): SmartButtonInterface {
        return $container->get('button.smart-button');
    }, $container->get('wcgateway.settings'), $container->get('wcgateway.settings.status'), $container->get('wcgateway.paypal-gateway'), $container->get('blocks.settings.final_review_enabled'), $container->get('session.cancellation.view'), $container->get('session.handler'), $container->get('wc-subscriptions.helper'), $container->get('blocks.add-place-order-method'), $container->get('wcgateway.use-place-order-button'), $container->get('wcgateway.place-order-button-text'), $container->get('wcgateway.place-order-button-description'), $container->get('wcgateway.all-funding-sources'));
}, 'blocks.advanced-card-method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Blocks\AdvancedCardPaymentMethod {
    return new \WooCommerce\PayPalCommerce\Blocks\AdvancedCardPaymentMethod($container->get('blocks.url'), $container->get('ppcp.asset-version'), $container->get('wcgateway.credit-card-gateway'), function () use ($container): SmartButtonInterface {
        return $container->get('button.smart-button');
    }, $container->get('wcgateway.settings'), $container->get('wcgateway.configuration.card-configuration'));
}, 'blocks.settings.final_review_enabled' => static function (ContainerInterface $container): bool {
    $settings = $container->get('wcgateway.settings');
    assert($settings instanceof ContainerInterface);
    return $settings->has('blocks_final_review_enabled') ? (bool) $settings->get('blocks_final_review_enabled') : \true;
}, 'blocks.endpoint.update-shipping' => static function (ContainerInterface $container): UpdateShippingEndpoint {
    return new UpdateShippingEndpoint($container->get('button.request-data'), $container->get('api.endpoint.order'), $container->get('api.factory.purchase-unit'), $container->get('woocommerce.logger.woocommerce'));
}, 'blocks.add-place-order-method' => function (ContainerInterface $container): bool {
    /**
     * Whether to create a non-express method with the standard "Place order" button redirecting to PayPal.
     */
    return apply_filters('woocommerce_paypal_payments_blocks_add_place_order_method', \true);
});
