<?php
/**
 * The local alternative payment methods module services.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'ppcp-local-apms.url'                       => static function ( ContainerInterface $container ): string {
		/**
		 * The path cannot be false.
		 *
		 * @psalm-suppress PossiblyFalseArgument
		 */
		return plugins_url(
			'/modules/ppcp-local-alternative-payment-methods/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'ppcp-local-apms.bancontact.wc-gateway'     => static function ( ContainerInterface $container ): BancontactGateway {
		return new BancontactGateway(
			$container->get( 'api.endpoint.orders' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'wcgateway.transaction-url-provider' )
		);
	},
	'ppcp-local-apms.blik.wc-gateway'           => static function ( ContainerInterface $container ): BlikGateway {
		return new BlikGateway(
			$container->get( 'api.endpoint.orders' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'wcgateway.transaction-url-provider' )
		);
	},
	'ppcp-local-apms.bancontact.payment-method' => static function( ContainerInterface $container ): BancontactPaymentMethod {
		return new BancontactPaymentMethod(
			$container->get( 'ppcp-local-apms.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'ppcp-local-apms.bancontact.wc-gateway' )
		);
	},
);
