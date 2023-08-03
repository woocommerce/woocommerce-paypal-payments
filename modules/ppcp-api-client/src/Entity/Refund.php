<?php
/**
 * The refund entity.
 *
 * @link https://developer.paypal.com/docs/api/orders/v2/#definition-refund
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Refund
 */
class Refund {

	/**
	 * The ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * The status.
	 *
	 * @var RefundStatus
	 */
	private $status;

	/**
	 * The amount.
	 *
	 * @var Amount
	 */
	private $amount;

	/**
	 * The detailed breakdown of the refund activity (fees, ...).
	 *
	 * @var SellerPayableBreakdown|null
	 */
	private $seller_payable_breakdown;

	/**
	 * The invoice id.
	 *
	 * @var string
	 */
	private $invoice_id;

	/**
	 * Returns the entity as array.
	 *
	 * @return array
	 */
	public function to_array() : array {
		$data    = array(
		);
		return $data;
	}
}
