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
	 * The SellerReceivableBreakdown factory.
	 *
	 * @var SellerReceivableBreakdownFactory
	 */
	private $seller_receivable_breakdown_factory;

	/**
	 * The FraudProcessorResponseFactory factory.
	 *
	 * @var FraudProcessorResponseFactory
	 */
	protected $fraud_processor_response_factory;

	/**
	 * CaptureFactory constructor.
	 *
	 * @param AmountFactory                    $amount_factory The amount factory.
	 * @param SellerReceivableBreakdownFactory $seller_receivable_breakdown_factory The SellerReceivableBreakdown factory.
	 * @param FraudProcessorResponseFactory    $fraud_processor_response_factory The FraudProcessorResponseFactory factory.
	 */
	public function __construct(
		AmountFactory $amount_factory,
		SellerReceivableBreakdownFactory $seller_receivable_breakdown_factory,
		FraudProcessorResponseFactory $fraud_processor_response_factory
	) {

		$this->amount_factory                      = $amount_factory;
		$this->seller_receivable_breakdown_factory = $seller_receivable_breakdown_factory;
		$this->fraud_processor_response_factory    = $fraud_processor_response_factory;
	}

	/**
	 * Returns the capture object based off the PayPal response.
	 *
	 * @param \stdClass $data The PayPal response.
	 *
	 * @return Capture
	 */
	public function from_paypal_response( \stdClass $data ) : Capture {
		$reason                      = $data->status_details->reason ?? null;
		$seller_receivable_breakdown = isset( $data->seller_receivable_breakdown ) ?
			$this->seller_receivable_breakdown_factory->from_paypal_response( $data->seller_receivable_breakdown )
			: null;

		$fraud_processor_response = isset( $data->processor_response ) ?
			$this->fraud_processor_response_factory->from_paypal_response( $data->processor_response )
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
			(string) $data->invoice_id,
			(string) $data->custom_id,
			$seller_receivable_breakdown,
			$fraud_processor_response
		);
	}
}
