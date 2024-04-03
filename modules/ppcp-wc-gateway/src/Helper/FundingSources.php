<?php
/**
 * The Checkout helper.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper;
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

/**
 * FundingSources class.
 */
class FundingSources {

	/**
	 * Returns all possible funding sources.
	 *
	 * @return array
	 */
	public static function all(): array {
		return array(
			'card'       => _x( 'Credit or debit cards', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'sepa'       => _x( 'SEPA-Lastschrift', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'bancontact' => _x( 'Bancontact', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'blik'       => _x( 'BLIK', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'eps'        => _x( 'eps', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'giropay'    => _x( 'giropay', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'ideal'      => _x( 'iDEAL', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'mybank'     => _x( 'MyBank', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'p24'        => _x( 'Przelewy24', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'venmo'      => _x( 'Venmo', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'trustly'    => _x( 'Trustly', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'paylater'   => _x( 'PayPal Pay Later', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'paypal'     => _x( 'PayPal', 'Name of payment method', 'woocommerce-paypal-payments' ),
		);
	}

	/**
	 * Returns extra funding sources.
	 *
	 * @return array
	 */
	public static function extra(): array {
		return array(
			'googlepay' => _x( 'Google Pay', 'Name of payment method', 'woocommerce-paypal-payments' ),
			'applepay'  => _x( 'Apple Pay', 'Name of payment method', 'woocommerce-paypal-payments' ),
		);
	}

	/**
	 * Returns extra funding sources.
	 *
	 * @return array
	 */
	public static function optional(): array {
		return array_diff_key(
			self::all(),
			array_flip(
				array(
					'paylater',
					'paypal',
				)
			)
		);
	}

}
