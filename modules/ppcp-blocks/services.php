<?php
/**
 * The blocks module services.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'blocks.url'    => static function ( ContainerInterface $container ): string {
		/**
		 * The path cannot be false.
		 *
		 * @psalm-suppress PossiblyFalseArgument
		 */
		return plugins_url(
			'/modules/ppcp-blocks/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'blocks.method' => static function ( ContainerInterface $container ): PayPalPaymentMethod {
		return new PayPalPaymentMethod(
			$container->get( 'blocks.url' ),
			$container->get( 'ppcp.asset-version' ),
			$container->get( 'button.smart-button' ),
			$container->get( 'wcgateway.paypal-gateway' )
		);
	},
);
