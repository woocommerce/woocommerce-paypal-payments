<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

class Amount {

	private Money $money;

	private ?AmountBreakdown $breakdown;

	public function __construct( Money $money, ?AmountBreakdown $breakdown = null ) {
		$this->money     = $money;
		$this->breakdown = $breakdown;
	}

	public function currency_code(): string {
		return $this->money->currency_code();
	}

	public function value(): float {
		return $this->money->value();
	}

	/**
	 * The value formatted as string for API requests.
	 *
	 * @return string
	 */
	public function value_str(): string {
		return $this->money->value_str();
	}

	public function breakdown(): ?AmountBreakdown {
		return $this->breakdown;
	}

	/**
	 * Returns the object as array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$amount = $this->money->to_array();
		if ( $this->breakdown() && count( $this->breakdown()->to_array() ) ) {
			$amount['breakdown'] = $this->breakdown()->to_array();
		}
		return $amount;
	}
}
