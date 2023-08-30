<?php
/**
 * The Googlepay module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay;

use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;


return array(

	'wcgateway.settings.fields' => function ( ContainerInterface $container, array $fields ): array {
		$insert_after = function( array $array, string $key, array $new ): array {
			$keys = array_keys( $array );
			$index = array_search( $key, $keys, true );
			$pos = false === $index ? count( $array ) : $index + 1;

			return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
		};

		return $insert_after(
			$fields,
			'allow_card_button_gateway',
			array(
				'googlepay_button_enabled' => array(
					'title'        => __( 'Google Pay Button', 'woocommerce-paypal-payments' ),
					'type'         => 'checkbox',
					'label'        => __( 'Enable Google Pay button', 'woocommerce-paypal-payments' ),
					'default'      => 'yes',
					'screens'      => array( State::STATE_ONBOARDED ),
					'gateway'      => 'paypal',
					'requirements' => array(),
				),
			)
		);
	},

);
