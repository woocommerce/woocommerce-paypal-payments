<?php

/**
 * The blocks module services.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Blocks;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\UpdateShippingEndpoint;
use WooCommerce\PayPalCommerce\PayLaterWCBlocks\HookedBlocksRegistrar;
use WooCommerce\PayPalCommerce\Settings\Data\PayLaterMessagingSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
return array(
    'blocks.asset_getter' => static function (ContainerInterface $container): AssetGetter {
        $factory = $container->get('assets.asset_getter_factory');
        assert($factory instanceof AssetGetterFactory);
        return $factory->for_module('ppcp-blocks');
    },
    'blocks.method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Blocks\PayPalPaymentMethod {
        return new \WooCommerce\PayPalCommerce\Blocks\PayPalPaymentMethod($container->get('blocks.asset_getter'), $container->get('ppcp.asset-version'), function () use ($container): SmartButtonInterface {
            return $container->get('button.smart-button');
        }, $container->get('settings.settings-provider'), $container->get('wcgateway.settings.status'), $container->get('wcgateway.paypal-gateway'), $container->get('blocks.settings.final_review_enabled'), $container->get('session.cancellation.view'), $container->get('session.handler'), $container->get('wc-subscriptions.helper'), $container->get('blocks.add-place-order-method'), $container->get('wcgateway.use-place-order-button'), $container->get('wcgateway.all-funding-sources'));
    },
    'blocks.advanced-card-method' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Blocks\AdvancedCardPaymentMethod {
        return new \WooCommerce\PayPalCommerce\Blocks\AdvancedCardPaymentMethod($container->get('blocks.asset_getter'), $container->get('ppcp.asset-version'), $container->get('wcgateway.credit-card-gateway'), function () use ($container): SmartButtonInterface {
            return $container->get('button.smart-button');
        }, $container->get('settings.settings-provider'), $container->get('wcgateway.configuration.card-configuration'), $container->get('save-payment-methods.eligible'), $container->get('settings.data.payment'), $container->get('wcgateway.credit-card-icons'));
    },
    'blocks.settings.final_review_enabled' => static function (ContainerInterface $container): bool {
        $settings_provider = $container->get('settings.settings-provider');
        assert($settings_provider instanceof SettingsProvider);
        return !$settings_provider->enable_pay_now();
    },
    'blocks.endpoint.update-shipping' => static function (ContainerInterface $container): UpdateShippingEndpoint {
        return $container->get('order-endpoints.endpoint.update-shipping');
    },
    'blocks.add-place-order-method' => function (ContainerInterface $container): bool {
        /**
         * Whether to create a non-express method with the standard "Place order" button redirecting to PayPal.
         */
        return apply_filters('woocommerce_paypal_payments_blocks_add_place_order_method', \true);
    },
    'blocks.product-messaging-renderer' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Blocks\ProductPayLaterMessagesRenderer {
        $paylater_settings = $container->get('settings.data.paylater-messaging-settings');
        assert($paylater_settings instanceof PayLaterMessagingSettings);
        $product = $paylater_settings->get_product();
        return new \WooCommerce\PayPalCommerce\Blocks\ProductPayLaterMessagesRenderer(array('layout' => $product->layout, 'position' => $product->logo_position, 'logo' => $product->logo_type, 'text_size' => $product->text_size, 'color' => $product->text_color, 'flex_color' => $product->flex_color, 'flex_ratio' => $product->flex_ratio));
    },
    'blocks.product-buttons-renderer' => static function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Blocks\ProductSmartButtonsRenderer {
        return new \WooCommerce\PayPalCommerce\Blocks\ProductSmartButtonsRenderer();
    },
    /**
     * Auto-inserts the product Smart Buttons and Pay Later messaging blocks after the
     * add-to-cart block in block-theme Single Product templates, via the Block Hooks API.
     * Each entry anchors against both add-to-cart block variants so it works whichever the
     * active template uses. The messaging entry is only added where Pay Later applies to the
     * merchant's country; every predicate is evaluated lazily at render time.
     */
    'blocks.product-hooked-blocks-registrar' => static function (ContainerInterface $container): HookedBlocksRegistrar {
        $settings_status = $container->get('wcgateway.settings.status');
        assert($settings_status instanceof SettingsStatus);
        $messages_apply = $container->get('button.helper.messages-apply');
        assert($messages_apply instanceof MessagesApply);
        $add_to_cart_anchors = array('woocommerce/add-to-cart-form', 'woocommerce/add-to-cart-with-options');
        // The predicate is settings-based only (no is_product() guard): Block Hooks evaluates
        // it while resolving the Single Product template in the Site Editor too, where
        // is_product() is false - guarding on it there would hide the blocks from the merchant
        // editing the template. The template renders only for products, and the front-end
        // render callbacks re-check context, so placement stays correct.
        $insertions = array(\WooCommerce\PayPalCommerce\Blocks\ProductBlocks::BUTTONS_BLOCK => array('anchor' => $add_to_cart_anchors, 'position' => 'after', 'enabled' => static function () use ($settings_status): bool {
            return \WooCommerce\PayPalCommerce\Blocks\ProductBlocks::is_buttons_enabled($settings_status);
        }));
        if ($messages_apply->for_country() && $container->has('paylater-configurator.factory.config')) {
            $insertions[\WooCommerce\PayPalCommerce\Blocks\ProductBlocks::MESSAGING_BLOCK] = array('anchor' => $add_to_cart_anchors, 'position' => 'after', 'enabled' => static function () use ($settings_status): bool {
                return \WooCommerce\PayPalCommerce\Blocks\ProductBlocks::is_messaging_enabled($settings_status);
            });
        }
        return new HookedBlocksRegistrar($insertions);
    },
);
