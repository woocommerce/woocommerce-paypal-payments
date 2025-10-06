<?php
/**
 * Defines a monetary amount.
 *
 * @see     https://github.com/paypal/agent-commerce/blob/28b799b0d11b6fb62f423e203de6ea4b9f2ce122/v1/docs/SCHEMA_REFERENCE.md#money
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
	private string $currency = '';

	private float $value = 0.;

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->currency = '';
		$this->value    = 0.;

		// Parse mandatory fields.
		if ( isset( $input['currency_code'] ) ) {
			$currency = trim( $input['currency_code'] );

			if ( 3 === strlen( $currency ) ) {
				$this->currency = strtoupper( $currency );
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

	public function currency(): string {
		return $this->currency;
	}

	public function value(): float {
		return $this->value;
	}
}
