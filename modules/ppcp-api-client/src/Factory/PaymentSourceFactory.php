<?php
/**
 * The PaymentSource factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\CardAuthenticationResult;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSourceCard;

/**
 * Class PaymentSourceFactory
 */
class PaymentSourceFactory {

	/**
	 * Returns a PaymentSource for a PayPal Response.
	 *
	 * @param \stdClass $data The JSON object.
	 *
	 * @return PaymentSource
	 */
	public function from_paypal_response( \stdClass $data ): PaymentSource {

		$card   = null;
		$wallet = null;
		if ( isset( $data->card ) ) {
			$authentication_result = null;
			if ( isset( $data->card->authentication_result ) ) {
				$authentication_result = new CardAuthenticationResult(
					isset( $data->card->authentication_result->liability_shift ) ?
						(string) $data->card->authentication_result->liability_shift : '',
					isset( $data->card->authentication_result->three_d_secure->enrollment_status ) ?
						(string) $data->card->authentication_result->three_d_secure->enrollment_status : '',
					isset( $data->card->authentication_result->three_d_secure->authentication_result ) ?
						(string) $data->card->authentication_result->three_d_secure->authentication_result : ''
				);
			}
			$card = new PaymentSourceCard(
				isset( $data->card->last_digits ) ? (string) $data->card->last_digits : '',
				isset( $data->card->brand ) ? (string) $data->card->brand : '',
				isset( $data->card->type ) ? (string) $data->card->type : '',
				$authentication_result
			);
		}
		return new PaymentSource( $card, $wallet );
	}
}
