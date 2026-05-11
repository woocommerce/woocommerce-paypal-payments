<?php
/**
 * Defines a monetary amount.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;

/**
 * @see MoneyTest - Unit tests for this class.
 */
class Money extends AgenticSchema {
	private ?string $currency = null;

	private ?float $value = null;

	protected function parse_fields( array $input, StoreValidation $validation ): void {
		// Reset all fields.
		$this->currency = null;
		$this->value    = null;

		// Parse mandatory fields.
		if ( isset( $input['currency_code'] ) && is_string( $input['currency_code'] ) ) {
			$currency = strtoupper( trim( $input['currency_code'] ) );

			if ( preg_match( '/^[A-Z]{3}$/', $currency ) ) {
				$this->currency = $currency;
			} else {
				$validation->add_invalid_data(
					'currency_code',
					'Unexpected currency_code',
					'Please provide a valid 3-letter currency code.'
				);
			}
		} else {
			$validation->add_missing_field( 'currency_code', 'Please provide a currency code.' );
		}

		if ( isset( $input['value'] ) ) {
			$value = $input['value'];

			if ( is_int( $value ) || is_float( $value ) ) {
				$this->value = (float) $value;
			} elseif ( is_string( $value ) && preg_match( '/^-?\d+(\.\d{2,3})?$/', $value ) ) {
				$this->value = (float) $value;
			} else {
				$validation->add_invalid_data(
					'value',
					'Unexpected money value',
					'Please provide a valid numerical value.'
				);
			}
		} else {
			$validation->add_missing_field( 'value', 'Please provide a value.' );
		}
	}

	public function currency_code(): ?string {
		return $this->currency;
	}

	public function value(): ?float {
		return $this->value;
	}
}
