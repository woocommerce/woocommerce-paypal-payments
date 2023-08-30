<?php
/**
 * The info about fees and amount that will be paid by the seller.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class SellerPayableBreakdown
 */
class SellerPayableBreakdown {

	/**
	 * The amount for this refunded payment in the currency of the transaction.
	 *
	 * @var Money|null
	 */
	private $gross_amount;

	/**
	 * The applicable fee for this refunded payment in the currency of the transaction.
	 *
	 * @var Money|null
	 */
	private $paypal_fee;

	/**
	 * The applicable fee for this captured payment in the receivable currency.
	 *
	 * Present only in cases the fee is charged in the receivable currency.
	 *
	 * @var Money|null
	 */
	private $paypal_fee_in_receivable_currency;

	/**
	 * The net amount that the payee receives for this refunded payment in their PayPal account.
	 *
	 * Computed as gross_amount minus the paypal_fee minus the platform_fees.
	 *
	 * @var Money|null
	 */
	private $net_amount;

	/**
	 * The net amount for this refunded payment in the receivable currency.
	 *
	 * @var Money|null
	 */
	private $net_amount_in_receivable_currency;

	/**
	 * The total amount for this refund.
	 *
	 * @var Money|null
	 */
	private $total_refunded_amount;

	/**
	 * An array of platform or partner fees, commissions, or brokerage fees that associated with the captured payment.
	 *
	 * @var PlatformFee[]
	 */
	private $platform_fees;

	/**
	 * SellerPayableBreakdown constructor.
	 *
	 * @param Money|null    $gross_amount The amount for this refunded payment in the currency of the transaction.
	 * @param Money|null    $paypal_fee The applicable fee for this refunded payment in the currency of the transaction.
	 * @param Money|null    $paypal_fee_in_receivable_currency The applicable fee for this refunded payment in the receivable currency.
	 * @param Money|null    $net_amount The net amount that the payee receives for this refunded payment in their PayPal account.
	 * @param Money|null    $net_amount_in_receivable_currency The net amount for this refunded payment in the receivable currency.
	 * @param Money|null    $total_refunded_amount The total amount for this refund.
	 * @param PlatformFee[] $platform_fees An array of platform or partner fees, commissions, or brokerage fees that associated with the captured payment.
	 */
	public function __construct(
		?Money $gross_amount,
		?Money $paypal_fee,
		?Money $paypal_fee_in_receivable_currency,
		?Money $net_amount,
		?Money $net_amount_in_receivable_currency,
		?Money $total_refunded_amount,
		array $platform_fees
	) {
		$this->gross_amount                      = $gross_amount;
		$this->paypal_fee                        = $paypal_fee;
		$this->paypal_fee_in_receivable_currency = $paypal_fee_in_receivable_currency;
		$this->net_amount                        = $net_amount;
		$this->net_amount_in_receivable_currency = $net_amount_in_receivable_currency;
		$this->total_refunded_amount             = $total_refunded_amount;
		$this->platform_fees                     = $platform_fees;
	}

	/**
	 * The amount for this refunded payment in the currency of the transaction.
	 *
	 * @return Money|null
	 */
	public function gross_amount(): ?Money {
		return $this->gross_amount;
	}

	/**
	 * The applicable fee for this refunded payment in the currency of the transaction.
	 *
	 * @return Money|null
	 */
	public function paypal_fee(): ?Money {
		return $this->paypal_fee;
	}

	/**
	 * The applicable fee for this refunded payment in the receivable currency.
	 *
	 * Present only in cases the fee is charged in the receivable currency.
	 *
	 * @return Money|null
	 */
	public function paypal_fee_in_receivable_currency(): ?Money {
		return $this->paypal_fee_in_receivable_currency;
	}

	/**
	 * The net amount that the payee receives for this refunded payment in their PayPal account.
	 *
	 * Computed as gross_amount minus the paypal_fee minus the platform_fees.
	 *
	 * @return Money|null
	 */
	public function net_amount(): ?Money {
		return $this->net_amount;
	}

	/**
	 * The net amount for this refunded payment in the receivable currency.
	 *
	 * @return Money|null
	 */
	public function net_amount_in_receivable_currency(): ?Money {
		return $this->net_amount_in_receivable_currency;
	}

	/**
	 * The total amount for this refund.
	 *
	 * @return Money|null
	 */
	public function total_refunded_amount(): ?Money {
		return $this->total_refunded_amount;
	}

	/**
	 * An array of platform or partner fees, commissions, or brokerage fees that associated with the refunded payment.
	 *
	 * @return PlatformFee[]
	 */
	public function platform_fees(): array {
		return $this->platform_fees;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array();
		if ( $this->gross_amount ) {
			$data['gross_amount'] = $this->gross_amount->to_array();
		}
		if ( $this->paypal_fee ) {
			$data['paypal_fee'] = $this->paypal_fee->to_array();
		}
		if ( $this->paypal_fee_in_receivable_currency ) {
			$data['paypal_fee_in_receivable_currency'] = $this->paypal_fee_in_receivable_currency->to_array();
		}
		if ( $this->net_amount ) {
			$data['net_amount'] = $this->net_amount->to_array();
		}
		if ( $this->net_amount_in_receivable_currency ) {
			$data['net_amount_in_receivable_currency'] = $this->net_amount_in_receivable_currency->to_array();
		}
		if ( $this->total_refunded_amount ) {
			$data['total_refunded_amount'] = $this->total_refunded_amount->to_array();
		}
		if ( $this->platform_fees ) {
			$data['platform_fees'] = array_map(
				function ( PlatformFee $fee ) {
					return $fee->to_array();
				},
				$this->platform_fees
			);
		}

		return $data;
	}
}
