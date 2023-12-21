<?php
/**
 * The Pay Later configurator module services.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterConfigurator
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterConfigurator;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'paylater-configurator.url' => static function ( ContainerInterface $container ): string {
		/**
		 * Cannot return false for this path.
		 *
		 * @psalm-suppress PossiblyFalseArgument
		 */
		return plugins_url(
			'/modules/ppcp-paylater-configurator/',
			dirname( realpath( __FILE__ ), 3 ) . '/woocommerce-paypal-payments.php'
		);
	},
);
