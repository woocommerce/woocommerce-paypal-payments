<?php
/**
 * The Pay Later WooCommerce Blocks module services.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterWCBlocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterWCBlocks;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'paylater-wc-blocks.url'      => static function ( ContainerInterface $container ): string {
		/**
		 * Cannot return false for this path.
		 *
		 * @psalm-suppress PossiblyFalseArgument
		 */
		return plugins_url(
			'/modules/ppcp-paylater-wc-blocks/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},

	'paylater-wc-blocks.renderer' => static function (): PayLaterWCBlocksRenderer {
		return new PayLaterWCBlocksRenderer();
	},
);
