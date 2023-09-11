<?php
/**
 * The Googlepay module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Googlepay;

use WooCommerce\PayPalCommerce\Googlepay\Helper\PropertiesDictionary;
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
				'googlepay_button_enabled'          => array(
					'title'             => __( 'Google Pay Button', 'woocommerce-paypal-payments' ),
					'type'              => 'checkbox',
					'label'             => __( 'Enable Google Pay button', 'woocommerce-paypal-payments' ),
					'default'           => 'yes',
					'screens'           => array( State::STATE_ONBOARDED ),
					'gateway'           => 'paypal',
					'requirements'      => array(),
					'custom_attributes' => array(
						'data-ppcp-handlers' => wp_json_encode(
							array(
								array(
									'handler' => 'SubElementsHandler',
									'options' => array(
										'values'   => array( '1' ),
										'elements' => array(
											'#field-googlepay_button_color',
											'#field-googlepay_button_type',
											'#field-googlepay_button_shipping_enabled',
										),
									),
								),
							)
						),
					),
				),
				'googlepay_button_color'            => array(
					'title'        => str_repeat( '&nbsp;', 6 ) . __( 'Button Color', 'woocommerce-paypal-payments' ),
					'type'         => 'select',
					'label'        => '',
					'input_class'  => array( 'wc-enhanced-select' ),
					'class'        => array(),
					'default'      => 'black',
					'options'      => PropertiesDictionary::button_colors(),
					'screens'      => array( State::STATE_ONBOARDED ),
					'gateway'      => 'paypal',
					'requirements' => array(),
				),
				'googlepay_button_type'             => array(
					'title'        => str_repeat( '&nbsp;', 6 ) . __( 'Button Type', 'woocommerce-paypal-payments' ),
					'type'         => 'select',
					'class'        => array(),
					'input_class'  => array( 'wc-enhanced-select' ),
					'default'      => 'pay',
					'options'      => PropertiesDictionary::button_types(),
					'screens'      => array( State::STATE_ONBOARDED ),
					'gateway'      => 'paypal',
					'requirements' => array(),
				),
				'googlepay_button_shipping_enabled' => array(
					'title'        => str_repeat( '&nbsp;', 6 ) . __( 'Shipping Callback', 'woocommerce-paypal-payments' ),
					'type'         => 'checkbox',
					'label'        => __( 'Enable Google Pay shipping callback', 'woocommerce-paypal-payments' ),
					'default'      => 'no',
					'screens'      => array( State::STATE_ONBOARDED ),
					'gateway'      => 'paypal',
					'requirements' => array(),
				),
			)
		);
	},

);
