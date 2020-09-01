<?php
/**
 * The Payee Factory.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Factory;

use Inpsyde\PayPalCommerce\ApiClient\Entity\Payee;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;

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
	public function from_paypal_response( \stdClass $data ): ?Payee {
		if ( ! isset( $data->email_address ) ) {
			throw new RuntimeException(
				__( 'No email for payee given.', 'paypal-for-woocommerce' )
			);
		}

		$merchant_id = ( isset( $data->merchant_id ) ) ? $data->merchant_id : '';
		return new Payee( $data->email_address, $merchant_id );
	}
}
