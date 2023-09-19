<?php
/**
 * The Applepay module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay;

use WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DisplayManager;


return array(
	'wcgateway.settings.fields' => function ( ContainerInterface $container, array $fields ): array {
		$insert_after = function ( array $array, string $key, array $new ): array {
			$keys = array_keys( $array );
			$index = array_search( $key, $keys, true );
			$pos = false === $index ? count( $array ) : $index + 1;

			return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
		};
		$display_manager = $container->get( 'wcgateway.display-manager' );
		assert( $display_manager instanceof DisplayManager );

		if ( ! $container->has( 'applepay.eligible' ) || ! $container->get( 'applepay.eligible' ) ) {
			$connection_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-connection#field-credentials_feature_onboarding_heading' );
			$connection_link = '<a href="' . $connection_url . '" target="_blank">';
			return $insert_after(
				$fields,
				'allow_card_button_gateway',
				array(
					'applepay_button_enabled' => array(
						'title'             => __( 'Apple Pay Button', 'woocommerce-paypal-payments' ),
						'type'              => 'checkbox',
						'class'             => array( 'ppcp-grayed-out-text' ),
						'input_class'       => array( 'ppcp-disabled-checkbox' ),
						'label'             => __( 'Enable Apple Pay button', 'woocommerce-paypal-payments' )
							. '<p class="description">'
							. sprintf(
							// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
								__( 'Your PayPal account  %1$srequires additional permissions%2$s to enable Apple Pay.', 'woocommerce-paypal-payments' ),
								$connection_link,
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
										->condition_element( 'applepay_button_enabled', '1' )
										->action_enable( 'applepay_button_enabled' )
										->to_array(),
								)
							),
						),
					),
				)
			);
		}

		return $insert_after(
			$fields,
			'allow_card_button_gateway',
			array(
				'applepay_button_enabled'  => array(
					'title'             => __( 'Apple Pay Button', 'woocommerce-paypal-payments' ),
					'type'              => 'checkbox',
					'label'             => __( 'Enable Apple Pay button', 'woocommerce-paypal-payments' )
						. '<p class="description">'
						. sprintf(
						// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
							__( 'Buyers can use %1$sApple Pay%2$s to make payments on the web using the Safari web browser or an iOS device.', 'woocommerce-paypal-payments' ),
							'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#apple-pay" target="_blank">',
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
									->condition_element( 'applepay_button_enabled', '1' )
									->action_visible( 'applepay_button_color' )
									->action_visible( 'applepay_button_type' )
									->action_visible( 'applepay_button_language' )
									->to_array(),
							)
						),
					),
				),
				'applepay_button_type'     => array(
					'title'        => str_repeat( '&nbsp;', 6 ) . __( 'Button Label', 'woocommerce-paypal-payments' ),
					'type'         => 'select',
					'desc_tip'     => true,
					'description'  => __(
						'This controls the label of the Apple Pay button.',
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
				'applepay_button_color'    => array(
					'title'        => str_repeat( '&nbsp;', 6 ) . __( 'Button Color', 'woocommerce-paypal-payments' ),
					'type'         => 'select',
					'desc_tip'     => true,
					'description'  => __(
						'The Apple Pay Button may appear as a black button with white lettering, white button with black lettering, or a white button with black lettering and a black outline.',
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
				'applepay_button_language' => array(
					'title'        => str_repeat( '&nbsp;', 6 ) . __( 'Button Language', 'woocommerce-paypal-payments' ),
					'type'         => 'select',
					'desc_tip'     => true,
					'description'  => __(
						'The language and region used for the displayed Apple Pay button. The default value is the current language and region setting in a browser.',
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
			)
		);
	},
);
