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

		$module_url = $container->get( 'axo.url' );

		// Standard Payments tab fields.
		return $insert_after(
			$fields,
			'vault_enabled_dcc',
			array(
				'axo_heading'      => array(
					'heading'      => __( 'Fastlane', 'woocommerce-paypal-payments' ),
					'type'         => 'ppcp-heading',
					'description'  => wp_kses_post(
						sprintf(
						// translators: %1$s and %2$s is a link tag.
							__(
								'Offer an accelerated checkout experience that recognizes guest shoppers and autofills their details so they can pay in seconds.',
								'woocommerce-paypal-payments'
							),
							'<a
                            rel="noreferrer noopener"
                            href="https://woo.com/document/woocommerce-paypal-payments/#vaulting-a-card"
                            >',
							'</a>'
						)
					),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(
						'dcc',
					),
					'gateway'      => 'dcc',
				),
				'axo_enabled'        => array(
					'title'             => __( 'Fastlane', 'woocommerce-paypal-payments' ),
					'title_html'        => sprintf(
						'<img src="%sassets/images/fastlane.png" alt="%s" style="max-width: 150px; max-height: 45px;" />',
						$module_url,
						__( 'Fastlane', 'woocommerce-paypal-payments' )
					),
					'type'              => 'checkbox',
					'label'             => __( 'Enable Fastlane by PayPal', 'woocommerce-paypal-payments' )
						. '<p class="description">'
						. __( 'Help accelerate checkout for guests with PayPal\'s autofill solution.', 'woocommerce-paypal-payments' )
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
								$display_manager
									->rule()
									->condition_js_variable( 'ppcpAxoShowStyles', true )
									->action_visible( 'axo_style_root_heading' )
									->action_visible( 'axo_style_root_bg_color' )
									->action_visible( 'axo_style_root_error_color' )
									->action_visible( 'axo_style_root_font_family' )
									->action_visible( 'axo_style_root_font_size_base' )
									->action_visible( 'axo_style_root_padding' )
									->action_visible( 'axo_style_root_primary_color' )
									->action_visible( 'axo_style_input_heading' )
									->action_visible( 'axo_style_input_bg_color' )
									->action_visible( 'axo_style_input_border_radius' )
									->action_visible( 'axo_style_input_border_color' )
									->action_visible( 'axo_style_root_border_width' )
									->action_visible( 'axo_style_root_text_color_base' )
									->action_visible( 'axo_style_root_focus_border_color' )
									->to_array(),
							)
						),
					),
					'classes'           => array( 'ppcp-valign-label-middle', 'ppcp-align-label-center' )
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
					'default'      => 'render',
					'options'      => PropertiesDictionary::email_widget_options(),
					'screens'      => array( State::STATE_ONBOARDED ),
					'gateway'      => 'dcc',
					'requirements' => array(),
				),
				//'axo_address_widget' => array(
				//	'title'        => __( 'Address Widget', 'woocommerce-paypal-payments' ),
				//	'type'         => 'select',
				//	'desc_tip'     => true,
				//	'description'  => __(
				//		'This controls if the Hosted Address Widget should be used.',
				//		'woocommerce-paypal-payments'
				//	),
				//	'classes'      => array( 'ppcp-field-indent' ),
				//	'class'        => array(),
				//	'input_class'  => array( 'wc-enhanced-select' ),
				//	'default'      => 'render',
				//	'options'      => PropertiesDictionary::address_widget_options(),
				//	'screens'      => array( State::STATE_ONBOARDED ),
				//	'gateway'      => 'dcc',
				//	'requirements' => array(),
				//),
				//'axo_payment_widget' => array(
				//	'title'        => __( 'Payment Widget', 'woocommerce-paypal-payments' ),
				//	'type'         => 'select',
				//	'desc_tip'     => true,
				//	'description'  => __(
				//		'This controls if the Hosted Payment Widget should be used.',
				//		'woocommerce-paypal-payments'
				//	),
				//	'label'        => '',
				//	'input_class'  => array( 'wc-enhanced-select' ),
				//	'classes'      => array( 'ppcp-field-indent' ),
				//	'class'        => array(),
				//	'default'      => 'render',
				//	'options'      => PropertiesDictionary::payment_widget_options(),
				//	'screens'      => array( State::STATE_ONBOARDED ),
				//	'gateway'      => 'dcc',
				//	'requirements' => array(),
				//),
				'axo_privacy'        => array(
					'title'        => __( 'Privacy', 'woocommerce-paypal-payments' ),
					'type'         => 'select',
					'desc_tip'     => true,
					'description'  => __(
						'This setting will control whether Fastlane branding is shown by email field.',
						'woocommerce-paypal-payments'
					),
					'classes'      => array( 'ppcp-field-indent' ),
					'class'        => array(),
					'input_class'  => array( 'wc-enhanced-select' ),
					'default'      => 'yes',
					'options'      => PropertiesDictionary::privacy_options(),
					'screens'      => array( State::STATE_ONBOARDED ),
					'gateway'      => 'dcc',
					'requirements' => array(),
				),
				'axo_style_heading'  => array(
					'heading'      => __( 'Advanced Style Settings (optional)', 'woocommerce-paypal-payments' ),
					'heading_html' => sprintf(
					// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
						__(
							'Advanced Style Settings (optional) %1$sSee more%2$s %3$sSee less%4$s',
							'woocommerce-paypal-payments'
						),
						'<a href="javascript:void(0)" id="ppcp-axo-style-more" onclick="jQuery(this).hide(); jQuery(\'#ppcp-axo-style-less\').show(); document.ppcpAxoShowStyles = true; jQuery(document).trigger(\'ppcp-display-change\');" style="font-weight: normal;">',
						'</a>',
						'<a href="javascript:void(0)" id="ppcp-axo-style-less" onclick="jQuery(this).hide(); jQuery(\'#ppcp-axo-style-more\').show(); document.ppcpAxoShowStyles = false; jQuery(document).trigger(\'ppcp-display-change\');" style="font-weight: normal; display: none;">',
						'</a>'
					),
					'type'         => 'ppcp-heading',
					'description'  => wp_kses_post(
						sprintf(
						// translators: %1$s and %2$s is a link tag.
							__(
								'Leave the default styling, or customize how Fastlane looks on your website. %1$sSee PayPal\'s developer docs%2$s for info',
								'woocommerce-paypal-payments'
							),
							'<a href="https://www.paypal.com/us/fastlane" target="_blank">',
							'</a>'
						)
					),
					'classes'      => array( 'ppcp-field-indent' ),
					'class'        => array(),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(
						'dcc',
					),
					'gateway'      => 'dcc',
				),

				'axo_style_root_heading' => array(
					'heading'      => __( 'Root Settings', 'woocommerce-paypal-payments' ),
					'type'         => 'ppcp-heading',
					'description'  => __(
						'These apply to the overall Fastlane checkout module.',
						'woocommerce-paypal-payments'
					),
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array( State::STATE_ONBOARDED ),
					'requirements' => array( 'dcc' ),
					'gateway'      => 'dcc',
				),
				'axo_style_root_bg_color' => array(
					'title'        => __( 'Background Color', 'woocommerce-paypal-payments' ),
					'type'         => 'text',
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(),
					'gateway'      => 'dcc',
				),
				'axo_style_root_error_color' => array(
					'title'        => __( 'Error Color', 'woocommerce-paypal-payments' ),
					'type'         => 'text',
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(),
					'gateway'      => 'dcc',
				),
				'axo_style_root_font_family' => array(
					'title'        => __( 'Font Family', 'woocommerce-paypal-payments' ),
					'type'         => 'text',
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(),
					'gateway'      => 'dcc',
				),
				'axo_style_root_font_size_base' => array(
					'title'        => __( 'Font Size Base', 'woocommerce-paypal-payments' ),
					'type'         => 'text',
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(),
					'gateway'      => 'dcc',
				),
				'axo_style_root_padding' => array(
					'title'        => __( 'Padding', 'woocommerce-paypal-payments' ),
					'type'         => 'text',
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(),
					'gateway'      => 'dcc',
				),
				'axo_style_root_primary_color' => array(
					'title'        => __( 'Primary Color', 'woocommerce-paypal-payments' ),
					'type'         => 'text',
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(),
					'gateway'      => 'dcc',
				),

				'axo_style_input_heading' => array(
					'heading'      => __( 'Input Settings', 'woocommerce-paypal-payments' ),
					'type'         => 'ppcp-heading',
					'description'  => __(
						'These apply to the customer input fields on your Fastlane module.',
						'woocommerce-paypal-payments'
					),
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array( State::STATE_ONBOARDED ),
					'requirements' => array( 'dcc' ),
					'gateway'      => 'dcc',
				),
				'axo_style_input_bg_color' => array(
					'title'        => __( 'Background Color', 'woocommerce-paypal-payments' ),
					'type'         => 'text',
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(),
					'gateway'      => 'dcc',
				),
				'axo_style_input_border_radius' => array(
					'title'        => __( 'Border Radius', 'woocommerce-paypal-payments' ),
					'type'         => 'text',
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(),
					'gateway'      => 'dcc',
				),
				'axo_style_input_border_color' => array(
					'title'        => __( 'Border Color', 'woocommerce-paypal-payments' ),
					'type'         => 'text',
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(),
					'gateway'      => 'dcc',
				),
				'axo_style_root_border_width' => array(
					'title'        => __( 'Border Width', 'woocommerce-paypal-payments' ),
					'type'         => 'text',
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(),
					'gateway'      => 'dcc',
				),
				'axo_style_root_text_color_base' => array(
					'title'        => __( 'Text Color Base', 'woocommerce-paypal-payments' ),
					'type'         => 'text',
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(),
					'gateway'      => 'dcc',
				),
				'axo_style_root_focus_border_color' => array(
					'title'        => __( 'Focus Border Color', 'woocommerce-paypal-payments' ),
					'type'         => 'text',
					'classes'      => array( 'ppcp-field-indent' ),
					'screens'      => array(
						State::STATE_ONBOARDED,
					),
					'requirements' => array(),
					'gateway'      => 'dcc',
				),

			)
		);
	},

);
