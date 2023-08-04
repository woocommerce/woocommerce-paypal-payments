<?php
/**
 * The refund factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Refund;
use WooCommerce\PayPalCommerce\ApiClient\Entity\RefundStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\RefundStatusDetails;

/**
 * Class RefundFactory
 */
class RefundFactory {

	/**
	 * The Amount factory.
	 *
	 * @var AmountFactory
	 */
	private $amount_factory;

	/**
	 * The SellerPayableBreakdownFactory factory.
	 *
	 * @var SellerPayableBreakdownFactory
	 */
	private $seller_payable_breakdown_factory;

	/**
	 * The RefundPayerFactory factory.
	 *
	 * @var RefundPayerFactory
	 */
	private $refund_payer_factory;

	/**
	 * RefundFactory constructor.
	 *
	 * @param AmountFactory                 $amount_factory The amount factory.
	 * @param SellerPayableBreakdownFactory $seller_payable_breakdown_factory The payable breakdown factory.
	 * @param RefundPayerFactory            $refund_payer_factory The payer breakdown factory.
	 */
	public function __construct(
		AmountFactory $amount_factory,
		SellerPayableBreakdownFactory $seller_payable_breakdown_factory,
		RefundPayerFactory $refund_payer_factory
	) {
		$this->amount_factory                   = $amount_factory;
		$this->seller_payable_breakdown_factory = $seller_payable_breakdown_factory;
		$this->refund_payer_factory             = $refund_payer_factory;
	}

	/**
	 * Returns the refund object based off the PayPal response.
	 *
	 * @param \stdClass $data The PayPal response.
	 *
	 * @return Refund
	 */
	public function from_paypal_response( \stdClass $data ) : Refund {
		$reason                   = $data->status_details->reason ?? null;
		$seller_payable_breakdown = isset( $data->seller_payable_breakdown ) ?
			$this->seller_payable_breakdown_factory->from_paypal_response( $data->seller_payable_breakdown )
			: null;

		$payer = isset( $data->payer ) ?
			$this->refund_payer_factory->from_paypal_response( $data->payer )
			: null;

		return new Refund(
			(string) $data->id,
			new RefundStatus(
				(string) $data->status,
				$reason ? new RefundStatusDetails( $reason ) : null
			),
			$this->amount_factory->from_paypal_response( $data->amount ),
			(string) ( $data->invoice_id ?? '' ),
			(string) ( $data->custom_id ?? '' ),
			$seller_payable_breakdown,
			(string) ( $data->acquirer_reference_number ?? '' ),
			(string) ( $data->note_to_payer ?? '' ),
			$payer
		);
	}
}
