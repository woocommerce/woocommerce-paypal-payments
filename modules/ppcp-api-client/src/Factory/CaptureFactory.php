<?php
/**
 * The capture factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use Woocommerce\PayPalCommerce\ApiClient\Entity\Capture;

/**
 * Class CaptureFactory
 */
class CaptureFactory {

	/**
	 * The Amount factory.
	 *
	 * @var AmountFactory
	 */
	private $amount_factory;

	/**
	 * CaptureFactory constructor.
	 *
	 * @param AmountFactory $amount_factory The amount factory.
	 */
	public function __construct( AmountFactory $amount_factory ) {

		$this->amount_factory = $amount_factory;
	}

	/**
	 * Returns the capture object based off the PayPal response.
	 *
	 * @param \stdClass $data The PayPal response.
	 *
	 * @return Capture
	 */
	public function from_paypal_response( \stdClass $data ) : Capture {

		$reason = isset( $data->status_details->reason ) ? (string) $data->status_details->reason : '';
		return new Capture(
			(string) $data->id,
			(string) $data->status,
			$reason,
			$this->amount_factory->from_paypal_response( $data->amount ),
			(bool) $data->final_capture,
			(string) $data->seller_protection->status,
			(string) $data->invoice_id,
			(string) $data->custom_id
		);
	}
}
