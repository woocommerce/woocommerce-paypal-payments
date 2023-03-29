<?php
/**
 * The blocks module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'wcgateway.button.locations'                       => function ( ContainerInterface $container, array $locations ): array {
		return array_merge(
			$locations,
			array(
				'checkout-block-express' => _x( 'Block Express Checkout', 'Name of Buttons Location', 'woocommerce-paypal-payments' ),
				'cart-block'             => _x( 'Block Cart', 'Name of Buttons Location', 'woocommerce-paypal-payments' ),
			)
		);
	},
	'wcgateway.settings.pay-later.messaging-locations' => function ( ContainerInterface $container, array $locations ): array {
		unset( $locations['checkout-block-express'] );
		unset( $locations['cart-block'] );
		return $locations;
	},
	'wcgateway.settings.pay-later.button-locations'    => function ( ContainerInterface $container, array $locations ): array {
		unset( $locations['checkout-block-express'] );
		unset( $locations['cart-block'] );
		return $locations;
	},
);
