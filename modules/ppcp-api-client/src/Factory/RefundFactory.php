<?php
/**
 * The payment refund factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Refund;

/**
 * Class RefundFactory
 */
class RefundFactory {

	/**
	 * RefundFactory constructor.
	 *
	 * @param AmountFactory                    $amount_factory The amount factory.
	 */
	public function __construct(
		AmountFactory $amount_factory
	) {
		$this->amount_factory                      = $amount_factory;

	}

	/**
	 * Returns the payment refund object based off the PayPal response.
	 *
	 * @param \stdClass $data The PayPal response.
	 *
	 * @return Refund
	 */
	public function from_paypal_response( \stdClass $data ) : Refund {

		return new Refund(
		);
	}
}
