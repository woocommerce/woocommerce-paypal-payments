<?php
/**
 * The Applepay module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Applepay;

use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;


return array(
	'wcgateway.settings.fields' => static function ( ContainerInterface $container, array $fields ): array {
		$fields['applepay_heading'] = array(
			'heading'      => __( 'Apple Pay', 'woocommerce-paypal-payments' ),
			'description'  =>
				__(
					'Customize the behaviour of the ApplePay button.',
					'woocommerce-paypal-payments'
				),
			'type'         => 'ppcp-heading',
			'screens'      => array( State::STATE_ONBOARDED ),
			'requirements' => array(),
			'gateway'      => 'paypal',
		);
		$fields['applepay_button_enabled_product'] = array(
			'title'        => __( 'Apple Pay Button on Product Page', 'woocommerce-paypal-payments' ),
			'type'         => 'checkbox',
			'label'        => __( 'Enable Apple Pay button on product page', 'woocommerce-paypal-payments' ),
			'default'      => 'yes',
			'screens'      => array( State::STATE_ONBOARDED ),
			'gateway'      => 'paypal',
			'requirements' => array(),
		);
		$fields['applepay_button_enabled_cart']    = array(
			'title'        => __( 'Apple Pay Button on Cart Page', 'woocommerce-paypal-payments' ),
			'type'         => 'checkbox',
			'label'        => __( 'Enable Apple Pay button on cart page', 'woocommerce-paypal-payments' ),
			'default'      => 'yes',
			'screens'      => array( State::STATE_ONBOARDED ),
			'gateway'      => 'paypal',
			'requirements' => array(),
		);
		$fields['applepay_live_validation_file'] = array(
			'title'        => __( 'Apple Pay Live Validation File', 'woocommerce-paypal-payments' ),
			'type'         => 'textarea',
			'label'        => __( 'Paste here the validation file content', 'woocommerce-paypal-payments' ),
			'default'      => null,
			'screens'      => array( State::STATE_ONBOARDED ),
			'gateway'      => 'paypal',
			'requirements' => array(),
		);
		$fields['applepay_sandbox_validation_file'] = array(
			'title'        => __( 'Apple Pay Sandbox Validation File', 'woocommerce-paypal-payments' ),
			'type'         => 'textarea',
			'label'        => __( 'Paste here the validation file content', 'woocommerce-paypal-payments' ),
			'default'      => null,
			'screens'      => array( State::STATE_ONBOARDED ),
			'gateway'      => 'paypal',
			'requirements' => array(),
		);



		return $fields;
	},
);
