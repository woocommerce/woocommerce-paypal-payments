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
	'ppcp-local-apms.payment-methods' => static function( ContainerInterface $container): array {
		return [
			'bancontact' => array(
				'id' => BancontactGateway::ID,
				'country' => 'BE',
				'currency' => 'EUR',
			),
			'blik' => array(
				'id' => BlikGateway::ID,
				'country' => 'PL',
				'currency' => 'PLN',
			),
			'eps' => array(
				'id' => EPSGateway::ID,
				'country' => 'AT',
				'currency' => 'EUR',
			),
			'ideal' => array(
				'id' => IDealGateway::ID,
				'country' => 'NL',
				'currency' => 'EUR',
			),
		];
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
	'ppcp-local-apms.eps.wc-gateway'            => static function ( ContainerInterface $container ): EPSGateway {
		return new EPSGateway(
			$container->get( 'api.endpoint.orders' ),
			$container->get( 'api.factory.purchase-unit' ),
			$container->get( 'wcgateway.processor.refunds' ),
			$container->get( 'wcgateway.transaction-url-provider' )
		);
	},
	'ppcp-local-apms.ideal.wc-gateway'            => static function ( ContainerInterface $container ): IDealGateway {
		return new IDealGateway(
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
	'ppcp-local-apms.blik.payment-method'       => static function( ContainerInterface $container ): BlikPaymentMethod {
		return new BlikPaymentMethod(
			$container->get( 'ppcp-local-apms.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'ppcp-local-apms.blik.wc-gateway' )
		);
	},
	'ppcp-local-apms.eps.payment-method'        => static function( ContainerInterface $container ): EPSPaymentMethod {
		return new EPSPaymentMethod(
			$container->get( 'ppcp-local-apms.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'ppcp-local-apms.eps.wc-gateway' )
		);
	},
	'ppcp-local-apms.ideal.payment-method'        => static function( ContainerInterface $container ): IDealPaymentMethod {
		return new IDealPaymentMethod(
			$container->get( 'ppcp-local-apms.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'ppcp-local-apms.ideal.wc-gateway' )
		);
	},
);
