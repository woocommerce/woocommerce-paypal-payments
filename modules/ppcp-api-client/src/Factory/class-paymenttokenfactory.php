<?php
/**
 * The PaymentToken Factory.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\PaymentToken;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class PaymentTokenFactory
 */
class PaymentTokenFactory {

	/**
	 * Returns a PaymentToken based off a PayPal Response object.
	 *
	 * @param \stdClass $data The JSON object.
	 *
	 * @return PaymentToken
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( \stdClass $data ): PaymentToken {
		if ( ! isset( $data->id ) ) {
			throw new RuntimeException(
				__( 'No id for payment token given', 'paypal-payments-for-woocommerce' )
			);
		}
		return new PaymentToken(
			$data->id,
			( isset( $data->type ) ) ? $data->type : PaymentToken::TYPE_PAYMENT_METHOD_TOKEN
		);
	}

	/**
	 * Creates a payment token based off a data array.
	 *
	 * @param array $data The data array.
	 *
	 * @return PaymentToken
	 */
	public function from_array( array $data ): PaymentToken {
		return $this->from_paypal_response( (object) $data );
	}
}
