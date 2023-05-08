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
			'smart_button_locations',
			array(
				'blocks_final_review_enabled' => array(
					'title'        => __( 'Require final confirmation on checkout', 'woocommerce-paypal-payments' ),
					'type'         => 'checkbox',
					'label'        => __(
						'Require customers to confirm express payments from the Block Cart and Block Express Checkout on the checkout page.
<p class="description">If this setting is not enabled, <a href="https://woocommerce.com/document/woocommerce-paypal-payments/#blocks-faq" target="_blank">payment confirmation on the checkout will be skipped</a>.
Skipping the final confirmation on the checkout page may impact the buyer experience during the PayPal payment process.</p>',
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
