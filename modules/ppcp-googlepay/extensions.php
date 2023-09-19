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
use WooCommerce\PayPalCommerce\WcGateway\Helper\DisplayManager;


return array(

	'wcgateway.settings.fields' => function ( ContainerInterface $container, array $fields ): array {

		// Eligibility check.
		if ( ! $container->has( 'googlepay.eligible' ) || ! $container->get( 'googlepay.eligible' ) ) {
			return $fields;
		}

		$insert_after = function( array $array, string $key, array $new ): array {
			$keys = array_keys( $array );
			$index = array_search( $key, $keys, true );
			$pos = false === $index ? count( $array ) : $index + 1;

			return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
		};

		$display_manager = $container->get( 'wcgateway.display-manager' );
		assert( $display_manager instanceof DisplayManager );

		return $insert_after(
			$fields,
			'allow_card_button_gateway',
			array(
				'googlepay_button_enabled'          => array(
					'title'             => __( 'Google Pay Button', 'woocommerce-paypal-payments' ),
					'type'              => 'checkbox',
					'label'             => __( 'Enable Google Pay button', 'woocommerce-paypal-payments' )
						. '<p class="description">'
						. sprintf(
							// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
							__( 'Buyers can use %1$sGoogle Pay%2$s to make payments on the web using a web browser.', 'woocommerce-paypal-payments' ),
							'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#google-pay" target="_blank">',
							'</a>'
						)
						. '</p>',
					'default'           => 'yes',
					'screens'           => array( State::STATE_ONBOARDED ),
					'gateway'           => 'paypal',
					'requirements'      => array(),
					'custom_attributes' => array(
						'data-ppcp-display' => wp_json_encode(
							array(
								$display_manager
									->rule()
									->condition_element( 'googlepay_button_enabled', '1' )
									->action_visible( 'googlepay_button_type' )
									->action_visible( 'googlepay_button_color' )
									->action_visible( 'googlepay_button_language' )
									->action_visible( 'googlepay_button_shipping_enabled' )
									->to_array(),
							)
						),
					),
				),
				'googlepay_button_type'             => array(
					'title'        => str_repeat( '&nbsp;', 6 ) . __( 'Button Label', 'woocommerce-paypal-payments' ),
					'type'         => 'select',
					'desc_tip'     => true,
					'description'  => __(
						'This controls the label of the Google Pay button.',
						'woocommerce-paypal-payments'
					),
					'class'        => array(),
					'input_class'  => array( 'wc-enhanced-select' ),
					'default'      => 'pay',
					'options'      => PropertiesDictionary::button_types(),
					'screens'      => array( State::STATE_ONBOARDED ),
					'gateway'      => 'paypal',
					'requirements' => array(),
				),
				'googlepay_button_color'            => array(
					'title'        => str_repeat( '&nbsp;', 6 ) . __( 'Button Color', 'woocommerce-paypal-payments' ),
					'type'         => 'select',
					'desc_tip'     => true,
					'description'  => __(
						'Google Pay payment buttons exist in two styles: dark and light. To provide contrast, use dark buttons on light backgrounds and light buttons on dark or colorful backgrounds.',
						'woocommerce-paypal-payments'
					),
					'label'        => '',
					'input_class'  => array( 'wc-enhanced-select' ),
					'class'        => array(),
					'default'      => 'black',
					'options'      => PropertiesDictionary::button_colors(),
					'screens'      => array( State::STATE_ONBOARDED ),
					'gateway'      => 'paypal',
					'requirements' => array(),
				),
				'googlepay_button_language'         => array(
					'title'        => str_repeat( '&nbsp;', 6 ) . __( 'Button Language', 'woocommerce-paypal-payments' ),
					'type'         => 'select',
					'desc_tip'     => true,
					'description'  => __(
						'The language and region used for the displayed Google Pay button. The default value is the current language and region setting in a browser.',
						'woocommerce-paypal-payments'
					),
					'class'        => array(),
					'input_class'  => array( 'wc-enhanced-select' ),
					'default'      => 'en',
					'options'      => PropertiesDictionary::button_languages(),
					'screens'      => array( State::STATE_ONBOARDED ),
					'gateway'      => 'paypal',
					'requirements' => array(),
				),
				'googlepay_button_shipping_enabled' => array(
					'title'        => str_repeat( '&nbsp;', 6 ) . __( 'Shipping Callback', 'woocommerce-paypal-payments' ),
					'type'         => 'checkbox',
					'desc_tip'     => true,
					'description'  => __(
						'Synchronizes your available shipping options with Google Pay. Enabling this may impact the buyer experience.',
						'woocommerce-paypal-payments'
					),
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
