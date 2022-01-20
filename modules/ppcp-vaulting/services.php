<?php
/**
 * The vaulting module services.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Vaulting\Assets\MyAccountPaymentsAssets;
use WooCommerce\PayPalCommerce\Vaulting\Endpoint\DeletePaymentTokenEndpoint;

return array(
	'vaulting.module-url'                => static function ( ContainerInterface $container ): string {
		return plugins_url(
			'/modules/ppcp-vaulting/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'vaulting.assets.myaccount-payments' => function( ContainerInterface $container ) : MyAccountPaymentsAssets {
		return new MyAccountPaymentsAssets(
			$container->get( 'vaulting.module-url' )
		);
	},
	'vaulting.payment-tokens-renderer'   => static function (): PaymentTokensRenderer {
		return new PaymentTokensRenderer();
	},
	'vaulting.repository.payment-token'  => static function ( ContainerInterface $container ): PaymentTokenRepository {
		$factory  = $container->get( 'api.factory.payment-token' );
		$endpoint = $container->get( 'api.endpoint.payment-token' );
		return new PaymentTokenRepository( $factory, $endpoint );
	},
	'vaulting.endpoint.delete'           => function( ContainerInterface $container ) : DeletePaymentTokenEndpoint {
		return new DeletePaymentTokenEndpoint(
			$container->get( 'vaulting.repository.payment-token' ),
			$container->get( 'button.request-data' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},
);
