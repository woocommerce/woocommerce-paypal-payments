<?php
/**
 * The blocks module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Blocks
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use WooCommerce\PayPalCommerce\Onboarding\State;
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

	'wcgateway.settings.fields'                        => function ( ContainerInterface $container, array $fields ): array {
		$insert_after = function( array $array, string $key, array $new ): array {
			$keys = array_keys( $array );
			$index = array_search( $key, $keys, true );
			$pos = false === $index ? count( $array ) : $index + 1;

			return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
		};

		return $insert_after(
			$fields,
			'smart_button_enable_styling_per_location',
			array(
				'blocks_final_review_enabled' => array(
					'title'        => __( 'Block Express payments Final Review', 'woocommerce-paypal-payments' ),
					'type'         => 'checkbox',
					'label'        => __(
						'Require customers to confirm Block Express payments on the Checkout page.
If disabled, the Checkout page is skipped for Block Express payments.
<p class="description">This can cause issues and poor UX if the server is not handling the requests fast enough.</p>',
						'woocommerce-paypal-payments'
					),
					'default'      => true,
					'screens'      => array( State::STATE_START, State::STATE_ONBOARDED ),
					'requirements' => array(),
					'gateway'      => 'paypal',
				),
			)
		);
	},

	'button.pay-now-contexts'                          => function ( ContainerInterface $container, array $contexts ): array {
		if ( ! $container->get( 'blocks.settings.final_review_enabled' ) ) {
			$contexts[] = 'checkout-block';
			$contexts[] = 'cart-block';
		}

		return $contexts;
	},

	'button.handle-shipping-in-paypal'                 => function ( ContainerInterface $container ): bool {
		return ! $container->get( 'blocks.settings.final_review_enabled' );
	},
);
