<?php
/**
 * The SEPA module services.
 *
 * @package WooCommerce\PayPalCommerce\SEPA
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WooCommerce\PayPalCommerce\SEPA\SEPAGateway;
use WooCommerce\PayPalCommerce\SEPA\SEPAPaymentMethod;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'sepa.url'                       => static function ( ContainerInterface $container ): string {
		/**
		 * The path cannot be false.
		 *
		 * @psalm-suppress PossiblyFalseArgument
		 */
		return plugins_url(
			'/modules/ppcp-sepa/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'sepa.wc-gateway' => static function ( ContainerInterface $container ): SEPAGateway {
		return new SEPAGateway(
			$container->get( 'api.endpoint.orders' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.order-processor' ),
			$container->get( 'api.factory.paypal-checkout-url' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'wcgateway.transaction-url-provider' ),
			$container->get( 'session.handler' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
	'sepa.payment-method' => static function( ContainerInterface $container ): SEPAPaymentMethod {
		return new SEPAPaymentMethod(
			$container->get( 'sepa.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'sepa.wc-gateway' )
		);
	},
);
