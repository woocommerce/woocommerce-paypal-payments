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
	'paypal-subscriptions.api-handler'              => static function( ContainerInterface $container ): SubscriptionsApiHandler {
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
	'paypal-subscriptions.module.url'               => static function ( ContainerInterface $container ): string {
		/**
		 * The path cannot be false.
		 *
		 * @psalm-suppress PossiblyFalseArgument
		 */
		return plugins_url(
			'/modules/ppcp-paypal-subscriptions/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
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
