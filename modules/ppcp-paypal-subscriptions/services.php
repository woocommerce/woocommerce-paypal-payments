<?php
/**
 * The PayPalSubscriptions module services.
 *
 * @package WooCommerce\PayPalCommerce\PayPalSubscriptions
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayPalSubscriptions;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'paypal-subscriptions.deactivate-plan-endpoint' => static function ( ContainerInterface $container ): DeactivatePlanEndpoint {
		return new DeactivatePlanEndpoint(
			$container->get( 'button.request-data' ),
			$container->get( 'api.endpoint.billing-plans' )
		);
	},
	'paypal-subscriptions.api-handler'              => static function ( ContainerInterface $container ): SubscriptionsApiHandler {
		return new SubscriptionsApiHandler(
			$container->get( 'api.endpoint.catalog-products' ),
			$container->get( 'api.factory.product' ),
			$container->get( 'api.endpoint.billing-plans' ),
			$container->get( 'api.factory.billing-cycle' ),
			$container->get( 'api.factory.payment-preferences' ),
			$container->get( 'api.shop.currency.getter' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'paypal-subscriptions.get_module_asset_url'     => static function ( ContainerInterface $container ): callable {
		/** @var $getter callable(string, string):string */
		$getter = $container->get( 'assets.get_module_asset_url' );

		return static function ( string $asset_name ) use ( $getter ): string {
			return ( $getter )( 'ppcp-paypal-subscriptions', $asset_name );
		};
	},
	'paypal-subscriptions.renewal-handler'          => static function ( ContainerInterface $container ): RenewalHandler {
		return new RenewalHandler( $container->get( 'woocommerce.logger.woocommerce' ) );
	},
	'paypal-subscriptions.status'                   => static function ( ContainerInterface $container ): SubscriptionStatus {
		return new SubscriptionStatus(
			$container->get( 'api.endpoint.billing-subscriptions' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
);
