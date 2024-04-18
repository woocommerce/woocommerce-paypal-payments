<?php
/**
 * The card authentication result factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CardAuthenticationResult;

/**
 * Class CardAuthenticationResultFactory
 */
class CardAuthenticationResultFactory {

	/**
	 * Returns a card authentication result from the given response object.
	 *
	 * @param stdClass $authentication_result The authentication result object.
	 * @return CardAuthenticationResult
	 */
	public function from_paypal_response( stdClass $authentication_result ): CardAuthenticationResult {
		return new CardAuthenticationResult(
			$authentication_result->liability_shift ?? '',
			$authentication_result->three_d_secure->enrollment_status ?? '',
			$authentication_result->three_d_secure->authentication_status ?? ''
		);
	}
}
