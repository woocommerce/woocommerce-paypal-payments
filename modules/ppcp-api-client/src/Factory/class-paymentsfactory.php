<?php
/**
 * The Payments factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;

/**
 * Class PaymentsFactory
 */
class PaymentsFactory {

	/**
	 * The Authorization factory.
	 *
	 * @var AuthorizationFactory
	 */
	private $authorization_factory;

	/**
	 * PaymentsFactory constructor.
	 *
	 * @param AuthorizationFactory $authorization_factory The AuthorizationFactory.
	 */
	public function __construct(
		AuthorizationFactory $authorization_factory
	) {

		$this->authorization_factory = $authorization_factory;
	}

	/**
	 * Returns a Payments object based off a PayPal response.
	 *
	 * @param \stdClass $data The JSON object.
	 *
	 * @return Payments
	 */
	public function from_paypal_response( \stdClass $data ): Payments {
		$authorizations = array_map(
			function ( \stdClass $authorization ): Authorization {
				return $this->authorization_factory->from_paypal_response( $authorization );
			},
			isset( $data->authorizations ) ? $data->authorizations : array()
		);
		$payments       = new Payments( ...$authorizations );
		return $payments;
	}
}
