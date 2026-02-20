<?php

/**
 * The blocks module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Blocks;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array('wcgateway.button.locations' => function (array $locations, ContainerInterface $container): array {
    return array_merge($locations, array('checkout-block-express' => did_action('init') ? _x('Express Checkout', 'Name of Buttons Location', 'woocommerce-paypal-payments') : 'Express Checkout', 'cart-block' => did_action('init') ? _x('Cart', 'Name of Buttons Location', 'woocommerce-paypal-payments') : 'Cart'));
}, 'wcgateway.settings.pay-later.messaging-locations' => function (array $locations, ContainerInterface $container): array {
    unset($locations['checkout-block-express']);
    unset($locations['cart-block']);
    return $locations;
}, 'button.pay-now-contexts' => function (array $contexts, ContainerInterface $container): array {
    if (!$container->get('blocks.settings.final_review_enabled')) {
        $contexts[] = 'checkout-block';
        $contexts[] = 'cart-block';
    }
    return $contexts;
}, 'button.handle-shipping-in-paypal' => function (bool $previous, ContainerInterface $container): bool {
    return !$container->get('blocks.settings.final_review_enabled');
});
