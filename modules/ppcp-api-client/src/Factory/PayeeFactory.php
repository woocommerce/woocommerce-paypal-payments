<?php
/**
 * The Payee Factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Payee;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class PayeeFactory
 */
class PayeeFactory {

	/**
	 * Returns a Payee object based off a PayPal Response.
	 *
	 * @param \stdClass $data The JSON object.
	 *
	 * @return Payee|null
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( \stdClass $data ) {
		$email       = ( isset( $data->email_address ) ) ? $data->email_address : '';
		$merchant_id = ( isset( $data->merchant_id ) ) ? $data->merchant_id : '';
		return new Payee( $email, $merchant_id );
	}
}
