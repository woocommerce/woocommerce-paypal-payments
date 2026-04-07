<?php
/**
 * WooCommerce Payment token factory.
 *
 * @package WooCommerce\PayPalCommerce\WcPaymentTokens
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcPaymentTokens;

/**
 * Class PaymentTokenFactory
 */
class PaymentTokenFactory {

	/**
	 * Creates a new WC payment token instance of the given type.
	 *
	 * @param string $type The type of WC payment token.
	 *
	 * @return void|PaymentTokenPayPal|PaymentTokenVenmo|PaymentTokenApplePay
	 */
	public function create( string $type ) {
		switch ( $type ) {
			case 'paypal':
				return new PaymentTokenPayPal();
			case 'venmo':
				return new PaymentTokenVenmo();
			case 'apple_pay':
				return new PaymentTokenApplePay();
		}
	}
}
