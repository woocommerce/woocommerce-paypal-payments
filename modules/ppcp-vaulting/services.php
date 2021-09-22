<?php
/**
 * The vaulting module services.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

use WooCommerce\PayPalCommerce\Vaulting\Assets\MyAccountPaymentsAssets;

return array(
	'vaulting.module-url'                => static function ( $container ): string {
		return plugins_url(
			'/modules/ppcp-vaulting/',
			dirname( __FILE__, 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'vaulting.assets.myaccount-payments' => function( $container ) : MyAccountPaymentsAssets {
		return new MyAccountPaymentsAssets(
			$container->get( 'vaulting.module-url' )
		);
	},
	'vaulting.payment-tokens-renderer'   => static function (): PaymentTokensRendered {
		return new PaymentTokensRendered();
	},
	'vaulting.repository.payment-token'  => static function ( $container ): PaymentTokenRepository {
		$factory  = $container->get( 'api.factory.payment-token' );
		$endpoint = $container->get( 'api.endpoint.payment-token' );
		return new PaymentTokenRepository( $factory, $endpoint );
	},
);
