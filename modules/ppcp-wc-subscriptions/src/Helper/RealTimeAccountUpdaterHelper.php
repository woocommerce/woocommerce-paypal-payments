<?php
/**
 * Real Time Account Updater helper class.
 *
 * @package WooCommerce\PayPalCommerce\SavePaymentMethods\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions\Helper;

use WC_Payment_Token;
use WC_Payment_Token_CC;

/**
 * Class RealTimeAccountUpdaterHelper
 */
class RealTimeAccountUpdaterHelper {

	/**
	 * Updates WC card token with the given data.
	 *
	 * @param string           $expiry Card expiry.
	 * @param string           $last_digits Card last 4 digits.
	 * @param WC_Payment_Token $token WC Payment Token.
	 * @return void
	 */
	public function update_wc_card_token( string $expiry, string $last_digits, WC_Payment_Token $token ): void {
		if (
			! is_a( $token, WC_Payment_Token_CC::class )
			|| $token->get_type() !== 'CC'
			|| ! in_array( $token->get_card_type(), array( 'VISA', 'MASTERCARD' ), true )
		) {
			return;
		}

		$wc_expiry = $token->get_expiry_month() . '-' . $token->get_expiry_year();
		if ( $expiry !== $wc_expiry ) {
			$expiry_split = explode( '-', $expiry );
			$token->set_expiry_year( $expiry_split[0] );
			$token->set_expiry_month( $expiry_split[1] );
			$token->save();
		}

		$wc_last_digits = $token->get_last4();
		if ( $last_digits !== $wc_last_digits ) {
			$token->set_last4( $last_digits );
			$token->save();
		}
	}
}
