<?php
/**
 * The platform fee object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class PlatformFee.
 */
class PlatformFee {

	/**
	 * The fee.
	 *
	 * @var Money
	 */
	private $amount;

	/**
	 * The recipient of the fee.
	 *
	 * @var Payee|null
	 */
	private $payee;

	/**
	 * PlatformFee constructor.
	 *
	 * @param Money      $amount The fee.
	 * @param Payee|null $payee The recipient of the fee.
	 */
	public function __construct( Money $amount, ?Payee $payee ) {
		$this->amount = $amount;
		$this->payee  = $payee;
	}

	/**
	 * The fee.
	 *
	 * @return Money
	 */
	public function amount(): Money {
		return $this->amount;
	}

	/**
	 * The recipient of the fee.
	 *
	 * @return Payee|null
	 */
	public function payee(): ?Payee {
		return $this->payee;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array(
			'amount' => $this->amount->to_array(),
		);
		if ( $this->payee ) {
			$data['payee'] = $this->payee->to_array();
		}
		return $data;
	}
}
