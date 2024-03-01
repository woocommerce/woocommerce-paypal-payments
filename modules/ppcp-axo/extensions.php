<?php
/**
 * The Axo module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Axo
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo;

use WooCommerce\PayPalCommerce\Axo\Helper\PropertiesDictionary;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DisplayManager;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

return array(

	'wcgateway.settings.fields' => function ( ContainerInterface $container, array $fields ): array {

		$insert_after = function( array $array, string $key, array $new ): array {
			$keys = array_keys( $array );
			$index = array_search( $key, $keys, true );
			$pos = false === $index ? count( $array ) : $index + 1;

			return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
		};

		$display_manager = $container->get( 'wcgateway.display-manager' );
		assert( $display_manager instanceof DisplayManager );

		// Standard Payments tab fields.
		return $insert_after(
			$fields,
			'vault_enabled_dcc',
			array(
				'axo_enabled'        => array(
					'title'             => __( 'Fastlane', 'woocommerce-paypal-payments' ),
					'type'              => 'checkbox',
					'label'             => __( 'Enable Fastlane Checkout', 'woocommerce-paypal-payments' )
						. '<p class="description">'
						. sprintf(
						// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
							__( 'Buyers can use %1$sFastlane%2$s to make payments.', 'woocommerce-paypal-payments' ),
							'<a href="https://www.paypal.com" target="_blank">',
							'</a>'
						)
						. '</p>',
					'default'           => 'yes',
					'screens'           => array( State::STATE_ONBOARDED ),
					'gateway'           => 'dcc',
					'requirements'      => array(),
					'custom_attributes' => array(
						'data-ppcp-display' => wp_json_encode(
							array(
								$display_manager
									->rule()
									->condition_element( 'axo_enabled', '1' )
									->action_visible( 'axo_gateway_title' )
									->action_visible( 'axo_email_widget' )
									->action_visible( 'axo_address_widget' )
									->action_visible( 'axo_payment_widget' )
									->action_class( 'axo_enabled', 'active' )
									->to_array(),
							)
						),
					),
				),
				'axo_gateway_title'  => array(
					'title'        => __( 'Gateway Title', 'woocommerce-paypal-payments' ),
					'type'         => 'text',
					'classes'      => array( 'ppcp-field-indent' ),
					'desc_tip'     => true,
					'description'  => __(
						'This controls the title of the Fastlane gateway the user sees on checkout.',
						'woocommerce-paypal-payments'
					),
					'default'      => __(
						'Fastlane Debit & Credit Cards',
						'woocommerce-paypal-payments'
					),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(),
					'gateway'      => 'dcc',
				),
				'axo_email_widget'   => array(
					'title'        => __( 'Email Widget', 'woocommerce-paypal-payments' ),
					'type'         => 'select',
					'desc_tip'     => true,
					'description'  => __(
						'This controls if the Hosted Email Widget should be used.',
						'woocommerce-paypal-payments'
					),
					'classes'      => array( 'ppcp-field-indent' ),
					'class'        => array(),
					'input_class'  => array( 'wc-enhanced-select' ),
					'default'      => 'pay',
					'options'      => PropertiesDictionary::email_widget_options(),
					'screens'      => array( State::STATE_ONBOARDED ),
					'gateway'      => 'dcc',
					'requirements' => array(),
				),
				'axo_address_widget' => array(
					'title'        => __( 'Address Widget', 'woocommerce-paypal-payments' ),
					'type'         => 'select',
					'desc_tip'     => true,
					'description'  => __(
						'This controls if the Hosted Address Widget should be used.',
						'woocommerce-paypal-payments'
					),
					'classes'      => array( 'ppcp-field-indent' ),
					'class'        => array(),
					'input_class'  => array( 'wc-enhanced-select' ),
					'default'      => 'pay',
					'options'      => PropertiesDictionary::address_widget_options(),
					'screens'      => array( State::STATE_ONBOARDED ),
					'gateway'      => 'dcc',
					'requirements' => array(),
				),
				'axo_payment_widget' => array(
					'title'        => __( 'Payment Widget', 'woocommerce-paypal-payments' ),
					'type'         => 'select',
					'desc_tip'     => true,
					'description'  => __(
						'This controls if the Hosted Payment Widget should be used.',
						'woocommerce-paypal-payments'
					),
					'label'        => '',
					'input_class'  => array( 'wc-enhanced-select' ),
					'classes'      => array( 'ppcp-field-indent' ),
					'class'        => array(),
					'default'      => 'black',
					'options'      => PropertiesDictionary::payment_widget_options(),
					'screens'      => array( State::STATE_ONBOARDED ),
					'gateway'      => 'dcc',
					'requirements' => array(),
				),
			)
		);
	},

);
