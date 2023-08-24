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
		$fields['googlepay_button_enabled'] = array(
			'title'        => __( 'Google Pay Button', 'woocommerce-paypal-payments' ),
			'type'         => 'checkbox',
			'label'        => __( 'Enable Google Pay button', 'woocommerce-paypal-payments' ),
			'default'      => 'yes',
			'screens'      => array( State::STATE_ONBOARDED ),
			'gateway'      => 'paypal',
			'requirements' => array(),
		);

		return $fields;
	},
);
