<?php
/**
 * The Payments factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use Woocommerce\PayPalCommerce\ApiClient\Entity\Capture;
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
	 * The Capture factory.
	 *
	 * @var CaptureFactory
	 */
	private $capture_factory;

	/**
	 * PaymentsFactory constructor.
	 *
	 * @param AuthorizationFactory $authorization_factory The Authorization factory.
	 * @param CaptureFactory       $capture_factory The Capture factory.
	 */
	public function __construct(
		AuthorizationFactory $authorization_factory,
		CaptureFactory $capture_factory
	) {

		$this->authorization_factory = $authorization_factory;
		$this->capture_factory       = $capture_factory;
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
		$captures       = array_map(
			function ( \stdClass $authorization ): Capture {
				return $this->capture_factory->from_paypal_response( $authorization );
			},
			isset( $data->captures ) ? $data->captures : array()
		);
		$payments       = new Payments( $authorizations, $captures );
		return $payments;
	}
}
