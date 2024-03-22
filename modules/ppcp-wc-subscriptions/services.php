<?php
/**
 * The services
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions;

use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcSubscriptions\Endpoint\SubscriptionChangePaymentMethod;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\RealTimeAccountUpdaterHelper;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

return array(
	'wc-subscriptions.helper'                            => static function ( ContainerInterface $container ): SubscriptionHelper {
		return new SubscriptionHelper();
	},
	'wc-subscriptions.helpers.real-time-account-updater' => static function ( ContainerInterface $container ) : RealTimeAccountUpdaterHelper {
		return new RealTimeAccountUpdaterHelper();
	},
	'wc-subscriptions.renewal-handler'                   => static function ( ContainerInterface $container ): RenewalHandler {
		$logger                = $container->get( 'woocommerce.logger.woocommerce' );
		$repository            = $container->get( 'vaulting.repository.payment-token' );
		$endpoint              = $container->get( 'api.endpoint.order' );
		$purchase_unit_factory = $container->get( 'api.factory.purchase-unit' );
		$payer_factory         = $container->get( 'api.factory.payer' );
		$environment           = $container->get( 'onboarding.environment' );
		$settings                      = $container->get( 'wcgateway.settings' );
		$authorized_payments_processor = $container->get( 'wcgateway.processor.authorized-payments' );
		$funding_source_renderer       = $container->get( 'wcgateway.funding-source.renderer' );
		return new RenewalHandler(
			$logger,
			$repository,
			$endpoint,
			$purchase_unit_factory,
			$container->get( 'api.factory.shipping-preference' ),
			$payer_factory,
			$environment,
			$settings,
			$authorized_payments_processor,
			$funding_source_renderer,
			$container->get( 'wc-subscriptions.helpers.real-time-account-updater' ),
			$container->get( 'wc-subscriptions.helper' ),
			$container->get( 'api.endpoint.payment-tokens' ),
			$container->get( 'vaulting.wc-payment-tokens' )
		);
	},
	'wc-subscriptions.repository.payment-token'          => static function ( ContainerInterface $container ): PaymentTokenRepository {
		$factory  = $container->get( 'api.factory.payment-token' );
		$endpoint = $container->get( 'api.endpoint.payment-token' );
		return new PaymentTokenRepository( $factory, $endpoint );
	},
	'wc-subscriptions.endpoint.subscription-change-payment-method' => static function( ContainerInterface $container ): SubscriptionChangePaymentMethod {
		return new SubscriptionChangePaymentMethod(
			$container->get( 'button.request-data' )
		);
	},
);
