<?php
/**
 * The SDK v6 module services.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6;

use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Assets\AssetGetterFactory;
use WooCommerce\PayPalCommerce\SdkV6\Assets\SdkV6Manager;
use WooCommerce\PayPalCommerce\SdkV6\Blocks\V6PaymentMethod;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\SimulateCartEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ApplePayConfig;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ButtonStyleMapper;
use WooCommerce\PayPalCommerce\SdkV6\Helper\GooglePayConfig;
use WooCommerce\PayPalCommerce\SdkV6\Helper\RateLimiter;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Builds a wallet's availability check from its own module's services.
 *
 * Reports false when that module is not loaded: its services are absent, so the
 * wallet cannot be offered. Each wallet keeps its own guards: the two modules
 * load independently.
 *
 * @param ContainerInterface $container The plugin container.
 * @param string             $module    The wallet module's service prefix.
 * @return callable(): bool
 */
$wallet_availability = static function ( ContainerInterface $container, string $module ): callable {
	return static function () use ( $container, $module ): bool {
		if ( ! $container->has( "$module.eligibility.check" ) || ! $container->has( "$module.available" ) ) {
			return false;
		}

		$is_eligible = $container->get( "$module.eligibility.check" );

		return $is_eligible() && $container->get( "$module.available" );
	};
};

return array(

	'sdk-v6.asset-getter'           => static function ( ContainerInterface $container ): AssetGetter {
		$factory = $container->get( 'assets.asset_getter_factory' );
		assert( $factory instanceof AssetGetterFactory );

		return $factory->for_module( 'ppcp-sdk-v6' );
	},

	'sdk-v6.button-style-mapper'    => static function ( ContainerInterface $container ): ButtonStyleMapper {
		return new ButtonStyleMapper(
			$container->get( 'settings.settings-provider' )
		);
	},

	'sdk-v6.google-pay-config'      => static function ( ContainerInterface $container ) use ( $wallet_availability ): GooglePayConfig {
		return new GooglePayConfig(
			$container->get( 'settings.settings-provider' ),
			$container->get( 'wc-subscriptions.helper' ),
			$wallet_availability( $container, 'googlepay' )
		);
	},

	'sdk-v6.apple-pay-config'       => static function ( ContainerInterface $container ) use ( $wallet_availability ): ApplePayConfig {
		return new ApplePayConfig(
			$container->get( 'settings.settings-provider' ),
			$container->get( 'wc-subscriptions.helper' ),
			$wallet_availability( $container, 'applepay' )
		);
	},

	/**
	 * Whether this module renders the PayPal stack on the current page.
	 *
	 * A callable rather than a bool: the answer depends on the query, which is
	 * unresolved while the container is being built. Exposed as a service so the
	 * wallet modules can ask without naming SdkV6Manager, which their own
	 * feature flags may leave unloaded.
	 */
	'sdk-v6.owns-current-page'      => static function ( ContainerInterface $container ): callable {
		return static function () use ( $container ): bool {
			$manager = $container->get( 'sdk-v6.manager' );
			assert( $manager instanceof SdkV6Manager );

			return $manager->should_load_on_current_page();
		};
	},

	'sdk-v6.manager'                => static function ( ContainerInterface $container ): SdkV6Manager {
		return new SdkV6Manager(
			$container->get( 'sdk-v6.asset-getter' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'settings.environment' ),
			$container->get( 'sdk-v6.button-style-mapper' ),
			$container->get( 'order-endpoints.handle-shipping-in-paypal' ),
			$container->get( 'wcgateway.settings.status' ),
			$container->get( 'button.helper.context' ),
			$container->get( 'session.handler' ),
			$container->get( 'session.cancellation.view' ),
			// Computed here rather than reusing blocks.settings.final_review_enabled
			// so this module does not depend on the ppcp-blocks module it replaces.
			! $container->get( 'settings.settings-provider' )->enable_pay_now(),
			$container->get( 'settings.settings-provider' )->save_paypal_and_venmo(),
			$container->get( 'wcgateway.configuration.card-configuration' ),
			$container->get( 'sdk-v6.google-pay-config' ),
			$container->get( 'sdk-v6.apple-pay-config' )
		);
	},

	'sdk-v6.endpoint.client-token'  => static function ( ContainerInterface $container ): ClientTokenEndpoint {
		return new ClientTokenEndpoint(
			$container->get( 'order-endpoints.request-data' ),
			$container->get( 'woocommerce.logger.woocommerce' ),
			$container->get( 'api.sdk-client-token' ),
			$container->get( 'sdk-v6.rate-limiter' )
		);
	},

	'sdk-v6.endpoint.simulate-cart' => static function ( ContainerInterface $container ): SimulateCartEndpoint {
		return new SimulateCartEndpoint(
			$container->get( 'order-endpoints.request-data' ),
			$container->get( 'order-endpoints.helper.cart-products' ),
			$container->get( 'button.helper.isolated-cart-simulator' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},

	'sdk-v6.rate-limiter'           => static function (): RateLimiter {
		return new RateLimiter(
			'ppcp_sdk_v6_rl_',
			10,
			60
		);
	},

	'sdk-v6.blocks.payment-method'  => static function ( ContainerInterface $container ): V6PaymentMethod {
		return new V6PaymentMethod(
			$container->get( 'sdk-v6.manager' ),
			$container->get( 'sdk-v6.asset-getter' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'wcgateway.paypal-gateway' )
		);
	},

);
