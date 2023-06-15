<?php
/**
 * The services
 *
 * @package WooCommerce\PayPalCommerce\Subscription
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription;

use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;

return array(
	'subscription.helper'                   => static function ( ContainerInterface $container ): SubscriptionHelper {
		return new SubscriptionHelper( $container->get( 'wcgateway.settings' ) );
	},
	'subscription.renewal-handler'          => static function ( ContainerInterface $container ): RenewalHandler {
		$logger                = $container->get( 'woocommerce.logger.woocommerce' );
		$repository            = $container->get( 'vaulting.repository.payment-token' );
		$endpoint              = $container->get( 'api.endpoint.order' );
		$purchase_unit_factory = $container->get( 'api.factory.purchase-unit' );
		$payer_factory         = $container->get( 'api.factory.payer' );
		$environment           = $container->get( 'onboarding.environment' );
		$settings                      = $container->get( 'wcgateway.settings' );
		$authorized_payments_processor = $container->get( 'wcgateway.processor.authorized-payments' );
		return new RenewalHandler(
			$logger,
			$repository,
			$endpoint,
			$purchase_unit_factory,
			$container->get( 'api.factory.shipping-preference' ),
			$payer_factory,
			$environment,
			$settings,
			$authorized_payments_processor
		);
	},
	'subscription.repository.payment-token' => static function ( ContainerInterface $container ): PaymentTokenRepository {
		$factory  = $container->get( 'api.factory.payment-token' );
		$endpoint = $container->get( 'api.endpoint.payment-token' );
		return new PaymentTokenRepository( $factory, $endpoint );
	},
	'subscription.api-handler'              => static function( ContainerInterface $container ): SubscriptionsApiHandler {
		return new SubscriptionsApiHandler(
			$container->get( 'api.endpoint.catalog-products' ),
			$container->get( 'api.factory.product' ),
			$container->get( 'api.endpoint.billing-plans' ),
			$container->get( 'api.factory.billing-cycle' ),
			$container->get( 'api.factory.payment-preferences' ),
			$container->get( 'api.shop.currency' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'subscription.module.url'               => static function ( ContainerInterface $container ): string {
		/**
		 * The path cannot be false.
		 *
		 * @psalm-suppress PossiblyFalseArgument
		 */
		return plugins_url(
			'/modules/ppcp-subscription/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'subscription.deactivate-plan-endpoint' => static function ( ContainerInterface $container ): DeactivatePlanEndpoint {
		return new DeactivatePlanEndpoint(
			$container->get( 'button.request-data' ),
			$container->get( 'api.endpoint.billing-plans' )
		);
	},
);
