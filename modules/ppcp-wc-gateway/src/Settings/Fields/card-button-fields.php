<?php
/**
 * The services of the Gateway module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;

return function ( ContainerInterface $container, array $fields ): array {

	$current_page_id = $container->get( 'wcgateway.current-ppcp-settings-page-id' );

	if ( $current_page_id !== CardButtonGateway::ID ) {
		return $fields;
	}

	$new_fields = array(
		'card_button_styling_heading'   => array(
			'heading'      => __( 'Standard Card Button Styling', 'woocommerce-paypal-payments' ),
			'description'  => sprintf(
			// translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
				__(
					'Customize the appearance of the Standard Card Button on the %1$sCheckout page%2$s.',
					'woocommerce-paypal-payments'
				),
				'<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-checkout" target="_blank">',
				'</a>',
				'<br />'
			),
			'type'         => 'ppcp-heading',
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => CardButtonGateway::ID,
		),
		'card_button_poweredby_tagline' => array(
			'title'        => __( 'Tagline', 'woocommerce-paypal-payments' ),
			'type'         => 'checkbox',
			'label'        => __( 'Enable "Powered by PayPal" tagline', 'woocommerce-paypal-payments' ),
			'default'      => false,
			'desc_tip'     => true,
			'description'  => __(
				'Add the "Powered by PayPal" line below the button.',
				'woocommerce-paypal-payments'
			),
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => CardButtonGateway::ID,
		),
		'card_button_color'             => array(
			'title'        => __( 'Color', 'woocommerce-paypal-payments' ),
			'type'         => 'select',
			'class'        => array(),
			'input_class'  => array( 'wc-enhanced-select' ),
			'default'      => 'black',
			'desc_tip'     => true,
			'description'  => __(
				'Controls the background color of the button. Change it to match your site design or aesthetic.',
				'woocommerce-paypal-payments'
			),
			'options'      => array(
				'black' => __( 'Black', 'woocommerce-paypal-payments' ),
				'white' => __( 'White', 'woocommerce-paypal-payments' ),
			),
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => CardButtonGateway::ID,
		),
		'card_button_shape'             => array(
			'title'        => __( 'Shape', 'woocommerce-paypal-payments' ),
			'type'         => 'select',
			'class'        => array(),
			'input_class'  => array( 'wc-enhanced-select' ),
			'default'      => 'rect',
			'desc_tip'     => true,
			'description'  => __(
				'The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.',
				'woocommerce-paypal-payments'
			),
			'options'      => array(
				'pill' => __( 'Pill', 'woocommerce-paypal-payments' ),
				'rect' => __( 'Rectangle', 'woocommerce-paypal-payments' ),
			),
			'screens'      => array(
				State::STATE_START,
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => CardButtonGateway::ID,
		),
		'card_button_preview'           => array(
			'type'         => 'ppcp-preview',
			'preview'      => array(
				'id'      => 'ppcpCardButtonPreview',
				'type'    => 'button',
				'message' => __( 'Standard Card Button Styling Preview', 'woocommerce-paypal-payments' ),
			),
			'screens'      => array(
				State::STATE_ONBOARDED,
			),
			'requirements' => array(),
			'gateway'      => CardButtonGateway::ID,
		),
	);

	return array_merge( $fields, $new_fields );
};
