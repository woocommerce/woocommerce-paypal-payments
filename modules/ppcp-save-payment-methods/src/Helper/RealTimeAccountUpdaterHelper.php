<?php
/**
 * Real Time Account Updater helper class.
 *
 * @package WooCommerce\PayPalCommerce\SavePaymentMethods\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SavePaymentMethods\Helper;

use stdClass;
use WC_Payment_Token;

/**
 * Class RealTimeAccountUpdaterHelper
 */
class RealTimeAccountUpdaterHelper {

	/**
	 * Updates WC Payment Token from PayPal response.
	 *
	 * @param stdClass         $order PayPal order response data.
	 * @param WC_Payment_Token $token WC Payment Token.
	 * @return void
	 */
	public function update_wc_token_from_paypal_response( stdClass $order, WC_Payment_Token $token ): void {
		if (
			$token->get_type() !== 'CC'
			|| ! in_array( $token->get_card_type(), array( 'VISA', 'MASTERCARD' ), true )
		) {
			return;
		}

		$expiry    = $order->payment_source->card->expiry ?? '';
		$wc_expiry = $token->get_expiry_month() . '-' . $token->get_expiry_year();

		if ( $expiry !== $wc_expiry ) {
			$expiry_split = explode( '-', $expiry );
			$token->set_expiry_year( $expiry_split[0] );
			$token->set_expiry_month( $expiry_split[1] );
			$token->save();
		}

		$last_digits    = $order->payment_source->card->last_digits ?? '';
		$wc_last_digits = $token->get_last4();
		if ( $last_digits !== $wc_last_digits ) {
			$token->set_last4( $last_digits );
			$token->save();
		}
	}
}
