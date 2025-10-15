<?php
/**
 * Defines a monetary amount.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\MissingField;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidData;

/**
 * @see MoneyTest - Unit tests for this class.
 */
class Money extends AgenticSchema {
	private ?string $currency = null;

	private ?float $value = null;

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->currency = null;
		$this->value    = null;

		// Parse mandatory fields.
		if ( isset( $input['currency_code'] ) && is_string( $input['currency_code'] ) ) {
			$currency = strtoupper( trim( $input['currency_code'] ) );

			if ( preg_match( '/^[A-Z]{3}$/', $currency ) ) {
				$this->currency = $currency;
			} else {
				$add_issue( new InvalidData( 'Unexpected currency_code', 'Please provide a valid 3-letter currency code.', 'currency_code' ) );
			}
		} else {
			$add_issue( new MissingField( 'Required field missing', 'Please provide a currency code.', 'currency_code' ) );
		}

		if ( isset( $input['value'] ) ) {
			$value = $input['value'];

			if ( is_int( $value ) || is_float( $value ) ) {
				$this->value = (float) $value;
			} elseif ( is_string( $value ) && preg_match( '/^-?\d+(\.\d{2,3})?$/', $value ) ) {
				$this->value = (float) $value;
			} else {
				$add_issue( new InvalidData( 'Unexpected money value', 'Please provide a valid numerical value.', 'value' ) );
			}
		} else {
			$add_issue( new MissingField( 'Required field missing', 'Please provide a value.', 'value' ) );
		}
	}

	public function currency(): ?string {
		return $this->currency;
	}

	public function value(): ?float {
		return $this->value;
	}
}
