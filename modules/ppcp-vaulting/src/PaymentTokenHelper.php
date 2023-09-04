<?php
/**
 * Payment Tokens helper methods.
 *
 * @package WooCommerce\PayPalCommerce\Vaulting
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vaulting;

/**
 * Class PaymentTokenHelper
 */
class PaymentTokenHelper {

	/**
	 * Checks if given PayPal token exist as WC Payment Token.
	 *
	 * @param array  $wc_tokens WC Payment Tokens.
	 * @param string $token_id PayPal Token ID.
	 * @return bool
	 */
	public function token_exist( array $wc_tokens, string $token_id ): bool {
		foreach ( $wc_tokens as $wc_token ) {
			if ( $wc_token->get_token() === $token_id ) {
				return true;
			}
		}

		return false;
	}
}
