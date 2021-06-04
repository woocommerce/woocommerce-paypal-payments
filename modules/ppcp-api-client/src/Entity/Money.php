<?php
/**
 * The money object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Money
 */
class Money {

	/**
	 * The currency code.
	 *
	 * @var string
	 */
	private $currency_code;

	/**
	 * The value.
	 *
	 * @var float
	 */
	private $value;

	/**
	 * Money constructor.
	 *
	 * @param float  $value The value.
	 * @param string $currency_code The currency code.
	 */
	public function __construct( float $value, string $currency_code ) {
		$this->value         = $value;
		$this->currency_code = $currency_code;
	}

	/**
	 * The value.
	 *
	 * @return float
	 */
	public function value(): float {
		return $this->value;
	}

	/**
	 * The currency code.
	 *
	 * @return string
	 */
	public function currency_code(): string {
		return $this->currency_code;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'currency_code' => $this->currency_code(),
			'value'         => number_format( $this->value(), 2, '.', '' ),
		);
	}
}
