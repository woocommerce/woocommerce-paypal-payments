<?php
/**
 * The PaymentToken Factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

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
				__( 'No id for payment token given', 'woocommerce-paypal-payments' )
			);
		}

		return new PaymentToken(
			$data->id,
			( isset( $data->type ) ) ? $data->type : PaymentToken::TYPE_PAYMENT_METHOD_TOKEN,
			$data->source
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
