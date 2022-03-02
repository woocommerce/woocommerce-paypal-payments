<?php
/**
 * The capture entity.
 *
 * @link https://developer.paypal.com/docs/api/orders/v2/#definition-capture
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Capture
 */
class Capture {

	/**
	 * The ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * The status.
	 *
	 * @var CaptureStatus
	 */
	private $status;

	/**
	 * The amount.
	 *
	 * @var Amount
	 */
	private $amount;

	/**
	 * Whether this is the final capture or not.
	 *
	 * @var bool
	 */
	private $final_capture;

	/**
	 * The seller protection.
	 *
	 * @var string
	 */
	private $seller_protection;

	/**
	 * The detailed breakdown of the capture activity (fees, ...).
	 *
	 * @var SellerReceivableBreakdown|null
	 */
	private $seller_receivable_breakdown;

	/**
	 * The invoice id.
	 *
	 * @var string
	 */
	private $invoice_id;

	/**
	 * The custom id.
	 *
	 * @var string
	 */
	private $custom_id;

	/**
	 * Capture constructor.
	 *
	 * @param string                         $id The ID.
	 * @param CaptureStatus                  $status The status.
	 * @param Amount                         $amount The amount.
	 * @param bool                           $final_capture The final capture.
	 * @param string                         $seller_protection The seller protection.
	 * @param string                         $invoice_id The invoice id.
	 * @param string                         $custom_id The custom id.
	 * @param SellerReceivableBreakdown|null $seller_receivable_breakdown The detailed breakdown of the capture activity (fees, ...).
	 */
	public function __construct(
		string $id,
		CaptureStatus $status,
		Amount $amount,
		bool $final_capture,
		string $seller_protection,
		string $invoice_id,
		string $custom_id,
		?SellerReceivableBreakdown $seller_receivable_breakdown
	) {

		$this->id                          = $id;
		$this->status                      = $status;
		$this->amount                      = $amount;
		$this->final_capture               = $final_capture;
		$this->seller_protection           = $seller_protection;
		$this->invoice_id                  = $invoice_id;
		$this->custom_id                   = $custom_id;
		$this->seller_receivable_breakdown = $seller_receivable_breakdown;
	}

	/**
	 * Returns the ID.
	 *
	 * @return string
	 */
	public function id() : string {
		return $this->id;
	}

	/**
	 * Returns the status.
	 *
	 * @return CaptureStatus
	 */
	public function status() : CaptureStatus {
		return $this->status;
	}

	/**
	 * Returns the amount.
	 *
	 * @return Amount
	 */
	public function amount() : Amount {
		return $this->amount;
	}

	/**
	 * Returns whether this is the final capture or not.
	 *
	 * @return bool
	 */
	public function final_capture() : bool {
		return $this->final_capture;
	}

	/**
	 * Returns the seller protection object.
	 *
	 * @return \stdClass
	 */
	public function seller_protection() : \stdClass {
		return (object) array( 'status' => $this->seller_protection );
	}

	/**
	 * Returns the invoice id.
	 *
	 * @return string
	 */
	public function invoice_id() : string {
		return $this->invoice_id;
	}

	/**
	 * Returns the custom id.
	 *
	 * @return string
	 */
	public function custom_id() : string {
		return $this->custom_id;
	}

	/**
	 * Returns the detailed breakdown of the capture activity (fees, ...).
	 *
	 * @return SellerReceivableBreakdown|null
	 */
	public function seller_receivable_breakdown() : ?SellerReceivableBreakdown {
		return $this->seller_receivable_breakdown;
	}

	/**
	 * Returns the entity as array.
	 *
	 * @return array
	 */
	public function to_array() : array {
		$data    = array(
			'id'                => $this->id(),
			'status'            => $this->status()->name(),
			'amount'            => $this->amount()->to_array(),
			'final_capture'     => $this->final_capture(),
			'seller_protection' => (array) $this->seller_protection(),
			'invoice_id'        => $this->invoice_id(),
			'custom_id'         => $this->custom_id(),
		);
		$details = $this->status()->details();
		if ( $details ) {
			$data['status_details'] = array( 'reason' => $details->reason() );
		}
		if ( $this->seller_receivable_breakdown ) {
			$data['seller_receivable_breakdown'] = $this->seller_receivable_breakdown->to_array();
		}
		return $data;
	}
}
