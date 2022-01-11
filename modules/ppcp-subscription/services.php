<?php
/**
 * The services
 *
 * @package WooCommerce\PayPalCommerce\Subscription
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription;

use WooCommerce\PayPalCommerce\Subscription\Helper\SubscriptionHelper;
use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;

return array(
	'subscription.helper'                   => static function ( ContainerInterface $container ): SubscriptionHelper {
		return new SubscriptionHelper();
	},
	'subscription.renewal-handler'          => static function ( ContainerInterface $container ): RenewalHandler {
		$logger                = $container->get( 'woocommerce.logger.woocommerce' );
		$repository            = $container->get( 'vaulting.repository.payment-token' );
		$endpoint              = $container->get( 'api.endpoint.order' );
		$purchase_unit_factory = $container->get( 'api.factory.purchase-unit' );
		$payer_factory         = $container->get( 'api.factory.payer' );
		$environment           = $container->get( 'onboarding.environment' );
		return new RenewalHandler(
			$logger,
			$repository,
			$endpoint,
			$purchase_unit_factory,
			$payer_factory,
			$environment
		);
	},
	'subscription.repository.payment-token' => static function ( ContainerInterface $container ): PaymentTokenRepository {
		$factory  = $container->get( 'api.factory.payment-token' );
		$endpoint = $container->get( 'api.endpoint.payment-token' );
		return new PaymentTokenRepository( $factory, $endpoint );
	},
);
