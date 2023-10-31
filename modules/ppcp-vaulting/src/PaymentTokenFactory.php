<?php
/**
 * WooCommerce Payment token factory.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

/**
 * Class PaymentTokenFactory
 */
class PaymentTokenFactory {

	/**
	 * Creates a new WC payment token instance of the given type.
	 *
	 * @param string $type The type of WC payment token.
	 *
	 * @return void|PaymentTokenPayPal
	 */
	public function create( string $type ) {
		switch ( $type ) {
			case 'paypal':
				return new PaymentTokenPayPal();
		}
	}
}
