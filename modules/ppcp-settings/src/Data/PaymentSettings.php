<?php
/**
 * PaymentSettings class
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Data;

use RuntimeException;

/**
 * This class serves as a container for managing the payment settings.
 */
class PaymentSettings extends AbstractDataModel {

	/**
	 * Option key where profile details are stored.
	 *
	 * @var string
	 */
	protected const OPTION_KEY = 'woocommerce-ppcp-data-payment-settings';

	/**
	 * Get default values for the model.
	 *
	 * @return array
	 */
	protected function get_defaults(): array {
		return array(
			'paypalCheckout' => array(
				'paypal'                         => array(
					'title'       => __( 'PayPal', 'woocommerce-paypal-payments' ),
					'description' => __(
						'Our all-in-one checkout solution lets you offer PayPal, Venmo, Pay Later options, and more to help maximize conversion.',
						'woocommerce-paypal-payments'
					),
					'icon'        => 'payment-method-paypal',
				),
				'venmo'                          => array(
					'title'       => __( 'Venmo', 'woocommerce-paypal-payments' ),
					'description' => __(
						'Offer Venmo at checkout to millions of active users.',
						'woocommerce-paypal-payments'
					),
					'icon'        => 'payment-method-venmo',
				),
				'paypal_credit'                  => array(
					'title'       => __( 'PayPal Credit', 'woocommerce-paypal-payments' ),
					'description' => __(
						'Get paid in full at checkout while giving your customers the option to pay interest free if paid within 6 months on orders over $99.',
						'woocommerce-paypal-payments'
					),
					'icon'        => 'payment-method-paypal',
				),
				'credit_and_debit_card_payments' => array(
					'title'       => __(
						'Credit and debit card payments',
						'woocommerce-paypal-payments'
					),
					'description' => __(
						"Accept all major credit and debit cards - even if your customer doesn't have a PayPal account.",
						'woocommerce-paypal-payments'
					),
					'icon'        => 'payment-method-cards',
				),
			),
		);
	}
}
