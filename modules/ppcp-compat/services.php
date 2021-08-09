<?php
/**
 * The compatibility module services.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

return array(

	'compat.ppec.mock-gateway'          => static function( $container ) {
		$settings = $container->get( 'wcgateway.settings' );
		$title    = $settings->has( 'title' ) ? $settings->get( 'title' ) : __( 'PayPal', 'woocommerce-paypal-payments' );
		$title    = sprintf(
			/* Translators: placeholder is the gateway name. */
			__( '%s (Legacy)', 'woocommerce-paypal-payments' ),
			$title
		);

		return new PPEC\MockGateway( $title );
	},

	'compat.ppec.subscriptions-handler' => static function ( $container ) {
		$ppcp_renewal_handler = $container->get( 'subscription.renewal-handler' );
		$gateway              = $container->get( 'compat.ppec.mock-gateway' );

		return new PPEC\SubscriptionsHandler( $ppcp_renewal_handler, $gateway );
	},

	'compat.ppec.settings_importer'     => static function( $container ) : PPEC\SettingsImporter {
		$settings = $container->get( 'wcgateway.settings' );

		return new PPEC\SettingsImporter( $settings );
	},

);
