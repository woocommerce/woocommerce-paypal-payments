<?php
/**
 * The Authorization factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\AuthorizationStatusDetails;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

/**
 * Class AuthorizationFactory
 */
class AuthorizationFactory {

	/**
	 * Returns an Authorization based off a PayPal response.
	 *
	 * @param \stdClass $data The JSON object.
	 *
	 * @return Authorization
	 * @throws RuntimeException When JSON object is malformed.
	 */
	public function from_paypal_response( \stdClass $data ): Authorization {
		if ( ! isset( $data->id ) ) {
			throw new RuntimeException(
				__( 'Does not contain an id.', 'woocommerce-paypal-payments' )
			);
		}

		if ( ! isset( $data->status ) ) {
			throw new RuntimeException(
				__( 'Does not contain status.', 'woocommerce-paypal-payments' )
			);
		}

		$reason = $data->status_details->reason ?? null;

		return new Authorization(
			$data->id,
			new AuthorizationStatus(
				$data->status,
				$reason ? new AuthorizationStatusDetails( $reason ) : null
			)
		);
	}
}
