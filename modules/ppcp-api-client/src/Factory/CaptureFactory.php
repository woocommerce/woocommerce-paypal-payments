<?php
/**
 * The capture factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatusDetails;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;

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

		$reason = $data->status_details->reason ?? null;

		$seller_receivable_breakdown = new \stdClass();

		$seller_receivable_breakdown->gross_amount = ( isset( $data->seller_receivable_breakdown->gross_amount ) ) ?
			new Money( (float) $data->seller_receivable_breakdown->gross_amount->value, $data->seller_receivable_breakdown->gross_amount->currency_code )
		: null;

		$seller_receivable_breakdown->paypal_fee = ( isset( $data->seller_receivable_breakdown->paypal_fee ) ) ?
			new Money( (float) $data->seller_receivable_breakdown->paypal_fee->value, $data->seller_receivable_breakdown->paypal_fee->currency_code )
			: null;

		$seller_receivable_breakdown->net_amount = ( isset( $data->seller_receivable_breakdown->net_amount ) ) ?
			new Money( (float) $data->seller_receivable_breakdown->net_amount->value, $data->seller_receivable_breakdown->net_amount->currency_code )
			: null;

		return new Capture(
			(string) $data->id,
			new CaptureStatus(
				(string) $data->status,
				$reason ? new CaptureStatusDetails( $reason ) : null
			),
			$this->amount_factory->from_paypal_response( $data->amount ),
			(bool) $data->final_capture,
			(string) $data->seller_protection->status,
			$seller_receivable_breakdown,
			(string) $data->invoice_id,
			(string) $data->custom_id
		);
	}
}
