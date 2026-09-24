<?php
/**
 * The Pay Later WooCommerce Blocks module services.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterWCBlocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\Settings\Data\PayLaterMessagingSettings;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;

return array(
	'paylater-wc-blocks.asset_getter'            => static function ( ContainerInterface $container ): AssetGetter {
		$factory = $container->get( 'assets.asset_getter_factory' );
		assert( $factory instanceof AssetGetterFactory );

		return $factory->for_module( 'ppcp-paylater-wc-blocks' );
	},

	/**
	 * Auto-inserts the cart and checkout Pay Later messaging blocks into block-theme
	 * templates via the Block Hooks API, so the messaging appears inside FSE cart and
	 * checkout templates and the Site Editor canvas without relying on classic hooks.
	 *
	 * Each insertion's `enabled` predicate is evaluated lazily, at render time, so it
	 * reflects the current Pay Later messaging placement settings. On a classic
	 * (non-block) theme the registrar is a no-op.
	 */
	'paylater-wc-blocks.hooked-blocks-registrar' => static function ( ContainerInterface $container ): HookedBlocksRegistrar {
		$settings_status = $container->get( 'wcgateway.settings.status' );
		assert( $settings_status instanceof SettingsStatus );

		return new HookedBlocksRegistrar(
			array(
				'woocommerce-paypal-payments/cart-paylater-messages'     => array(
					'anchor'   => 'woocommerce/cart-totals-block',
					'position' => 'last_child',
					'enabled'  => static function () use ( $settings_status ): bool {
						return PayLaterWCBlocksModule::is_placement_enabled( $settings_status, 'cart' );
					},
				),
				'woocommerce-paypal-payments/checkout-paylater-messages' => array(
					'anchor'   => 'woocommerce/checkout-totals-block',
					'position' => 'last_child',
					'enabled'  => static function () use ( $settings_status ): bool {
						return PayLaterWCBlocksModule::is_placement_enabled( $settings_status, 'checkout' );
					},
				),
			)
		);
	},

	'paylater-wc-blocks.cart-renderer'           => static function ( ContainerInterface $container ): PayLaterWCBlocksRenderer {
		$paylater_settings = $container->get( 'settings.data.paylater-messaging-settings' );
		assert( $paylater_settings instanceof PayLaterMessagingSettings );
		$cart = $paylater_settings->get_cart();
		return new PayLaterWCBlocksRenderer(
			array(
				'placement'  => 'cart',
				'layout'     => $cart->layout,
				'position'   => $cart->logo_position,
				'logo'       => $cart->logo_type,
				'text_size'  => $cart->text_size,
				'color'      => $cart->text_color,
				'flex_color' => $cart->flex_color,
				'flex_ratio' => $cart->flex_ratio,
			)
		);
	},
	'paylater-wc-blocks.checkout-renderer'       => static function ( ContainerInterface $container ): PayLaterWCBlocksRenderer {
		$paylater_settings = $container->get( 'settings.data.paylater-messaging-settings' );
		assert( $paylater_settings instanceof PayLaterMessagingSettings );
		$checkout = $paylater_settings->get_checkout();
		return new PayLaterWCBlocksRenderer(
			array(
				'placement'  => 'payment',
				'layout'     => $checkout->layout,
				'position'   => $checkout->logo_position,
				'logo'       => $checkout->logo_type,
				'text_size'  => $checkout->text_size,
				'color'      => $checkout->text_color,
				'flex_color' => $checkout->flex_color,
				'flex_ratio' => $checkout->flex_ratio,
			)
		);
	},
);
