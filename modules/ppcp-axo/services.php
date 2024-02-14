<?php
/**
 * The Axo module services.
 *
 * @package WooCommerce\PayPalCommerce\Axo
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo;

use WooCommerce\PayPalCommerce\Axo\Assets\AxoManager;
use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(

	'axo.url' => static function ( ContainerInterface $container ): string {
		$path = realpath( __FILE__ );
		if ( false === $path ) {
			return '';
		}
		return plugins_url(
			'/modules/ppcp-axo/',
			dirname( $path, 3 ) . '/woocommerce-paypal-payments.php'
		);
	},

	'axo.manager' => static function ( ContainerInterface $container ): AxoManager {
		return new AxoManager(
			$container->get( 'axo.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'session.handler' ),
			$container->get( 'wcgateway.settings' ),
			$container->get( 'onboarding.environment' ),
			$container->get( 'wcgateway.settings.status' ),
			$container->get( 'api.shop.currency' ),
			$container->get( 'woocommerce.logger.woocommerce' )
		);
	},

	'axo.gateway' => static function ( ContainerInterface $container ): AxoGateway {
		return new AxoGateway(
			$container->get( 'wcgateway.settings' )
		);
	},

);
