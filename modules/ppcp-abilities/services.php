<?php
/**
 * The abilities module services.
 *
 * @package WooCommerce\PayPalCommerce\Abilities
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Abilities;

use Psr\Log\LoggerInterface;
use Throwable;
use WooCommerce\PayPalCommerce\Abilities\Handler\GetConnectionStatusHandler;
use WooCommerce\PayPalCommerce\Abilities\Handler\GetOrderTrackingHandler;
use WooCommerce\PayPalCommerce\Abilities\Handler\GetPaymentMethodsHandler;
use WooCommerce\PayPalCommerce\Abilities\Handler\GetPaypalOrderHandler;
use WooCommerce\PayPalCommerce\Abilities\Helper\EnvelopeParser;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;

return array(
	'abilities.registrar'                     => static function ( ContainerInterface $container ): AbilitiesRegistrar {
		return new AbilitiesRegistrar();
	},
	/**
	 * The logger every ability service writes to.
	 *
	 * Falls back to a NullLogger when the plugin logger cannot be resolved:
	 * logging is a side channel for this module, so a broken logger must
	 * silence the log lines, not take the whole abilities surface down with a
	 * container failure that AbilityHandlers::callback() would then surface as
	 * `service_unavailable`.
	 */
	'abilities.logger'                        => static function ( ContainerInterface $container ): LoggerInterface {
		try {
			$logger = $container->get( 'woocommerce.logger.woocommerce' );
		} catch ( Throwable $exception ) {
			return new NullLogger();
		}

		return $logger instanceof LoggerInterface ? $logger : new NullLogger();
	},
	'abilities.envelope-parser'               => static function ( ContainerInterface $container ): EnvelopeParser {
		return new EnvelopeParser(
			$container->get( 'abilities.logger' )
		);
	},
	'abilities.handler.get-connection-status' => static function ( ContainerInterface $container ): GetConnectionStatusHandler {
		return new GetConnectionStatusHandler(
			$container->get( 'settings.rest.common' ),
			$container->get( 'abilities.envelope-parser' ),
			$container->get( 'abilities.logger' )
		);
	},
	'abilities.handler.get-payment-methods'   => static function ( ContainerInterface $container ): GetPaymentMethodsHandler {
		return new GetPaymentMethodsHandler(
			$container->get( 'settings.rest.payment' ),
			$container->get( 'abilities.envelope-parser' ),
			$container->get( 'abilities.logger' )
		);
	},
	'abilities.handler.get-order-tracking'    => static function ( ContainerInterface $container ): GetOrderTrackingHandler {
		return new GetOrderTrackingHandler(
			$container->get( 'order-tracking.endpoint.controller' ),
			$container->get( 'abilities.logger' )
		);
	},
	'abilities.handler.get-paypal-order'      => static function ( ContainerInterface $container ): GetPaypalOrderHandler {
		return new GetPaypalOrderHandler(
			$container->get( 'api.endpoint.order.cached' ),
			$container->get( 'abilities.logger' )
		);
	},
);
