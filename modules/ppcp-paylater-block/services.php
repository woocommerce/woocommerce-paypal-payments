<?php
/**
 * The Pay Later block module services.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterBlock
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterBlock;

use WooCommerce\PayPalCommerce\PayLaterBlock\PayLaterBlockRenderer;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'paylater-block.url'      => static function ( ContainerInterface $container ): string {
		/**
		 * Cannot return false for this path.
		 *
		 * @psalm-suppress PossiblyFalseArgument
		 */
		return plugins_url(
			'/modules/ppcp-paylater-block/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
	'paylater-block.renderer' => static function (): PayLaterBlockRenderer {
		return new PayLaterBlockRenderer();
	},
);
