<?php
/**
 * The exchange rate object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class ExchangeRate.
 */
class ExchangeRate {

	/**
	 * The source currency from which to convert an amount.
	 *
	 * @var string
	 */
	private $source_currency;

	/**
	 * The target currency to which to convert an amount.
	 *
	 * @var string
	 */
	private $target_currency;

	/**
	 * The target currency amount. Equivalent to one unit of the source currency.
	 *
	 * @var string
	 */
	private $value;

	/**
	 * ExchangeRate constructor.
	 *
	 * @param string $source_currency The source currency from which to convert an amount.
	 * @param string $target_currency The target currency to which to convert an amount.
	 * @param string $value The target currency amount. Equivalent to one unit of the source currency.
	 */
	public function __construct( string $source_currency, string $target_currency, string $value ) {
		$this->source_currency = $source_currency;
		$this->target_currency = $target_currency;
		$this->value           = $value;
	}

	/**
	 * The source currency from which to convert an amount.
	 *
	 * @return string
	 */
	public function source_currency(): string {
		return $this->source_currency;
	}

	/**
	 * The target currency to which to convert an amount.
	 *
	 * @return string
	 */
	public function target_currency(): string {
		return $this->target_currency;
	}

	/**
	 * The target currency amount. Equivalent to one unit of the source currency.
	 *
	 * @return string
	 */
	public function value(): string {
		return $this->value;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'source_currency' => $this->source_currency,
			'target_currency' => $this->target_currency,
			'value'           => $this->value,
		);
	}
}
