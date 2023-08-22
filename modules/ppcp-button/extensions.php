<?php
/**
 * The button module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Button
 */

declare(strict_types=1);

use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

return array(
	'wcgateway.settings.fields' => static function ( ContainerInterface $container, array $fields ): array {
		$fields['googlepay_heading'] = array(
			'heading'      => __( 'Google Pay', 'woocommerce-paypal-payments' ),
			'description'  =>
				__(
					'Customize the behaviour of the GooglePay button.',
					'woocommerce-paypal-payments'
				),
			'type'         => 'ppcp-heading',
			'screens'      => array( State::STATE_ONBOARDED ),
			'requirements' => array(),
			'gateway'      => 'paypal',
		);
		$fields['googlepay_button_enabled_product'] = array(
			'title'        => __( 'Google Pay Button on Product Page', 'woocommerce-paypal-payments' ),
			'type'         => 'checkbox',
			'label'        => __( 'Enable Google Pay button on product page', 'woocommerce-paypal-payments' ),
			'default'      => 'yes',
			'screens'      => array( State::STATE_ONBOARDED ),
			'gateway'      => 'paypal',
			'requirements' => array(),
		);
		$fields['googlepay_button_enabled_cart']    = array(
			'title'        => __( 'Google Pay Button on Cart Page', 'woocommerce-paypal-payments' ),
			'type'         => 'checkbox',
			'label'        => __( 'Enable Google Pay button on cart page', 'woocommerce-paypal-payments' ),
			'default'      => 'yes',
			'screens'      => array( State::STATE_ONBOARDED ),
			'gateway'      => 'paypal',
			'requirements' => array(),
		);

		return $fields;
	},
);
