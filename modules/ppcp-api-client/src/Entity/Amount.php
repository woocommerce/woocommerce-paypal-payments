<?php
/**
 * The amount object
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Amount
 */
class Amount {

	/**
	 * The money.
	 *
	 * @var Money
	 */
	private $money;

	/**
	 * The breakdown.
	 *
	 * @var AmountBreakdown
	 */
	private $breakdown;

	/**
	 * Amount constructor.
	 *
	 * @param Money                $money The money.
	 * @param AmountBreakdown|null $breakdown The breakdown.
	 */
	public function __construct( Money $money, AmountBreakdown $breakdown = null ) {
		$this->money     = $money;
		$this->breakdown = $breakdown;
	}

	/**
	 * Returns the currency code.
	 *
	 * @return string
	 */
	public function currency_code(): string {
		return $this->money->currency_code();
	}

	/**
	 * Returns the value.
	 *
	 * @return float
	 */
	public function value(): float {
		return $this->money->value();
	}

	/**
	 * Returns the breakdown.
	 *
	 * @return AmountBreakdown|null
	 */
	public function breakdown() {
		return $this->breakdown;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$amount = array(
			'currency_code' => $this->currency_code(),
			'value'         => number_format( $this->value(), 2, '.', '' ),
		);
		if ( $this->breakdown() && count( $this->breakdown()->to_array() ) ) {
			$amount['breakdown'] = $this->breakdown()->to_array();
		}
		return $amount;
	}
}
