<?php
/**
 * Defines the cart totals schema.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;

/**
 * @see CartTotalsTest - Unit tests for this class.
 */
class CartTotals extends AgenticSchema {
	private ?Money $total = null;

	private ?Money $subtotal = null;

	private ?Money $discount = null;

	private ?Money $shipping = null;

	private ?Money $tax = null;

	private ?Money $handling = null;

	private ?Money $insurance = null;

	private ?Money $shipping_discount = null;

	private ?Money $custom_charges = null;

	protected function parse_fields( array $input, StoreValidation $validation ): void {
		// Reset all fields.
		$this->total             = null;
		$this->subtotal          = null;
		$this->discount          = null;
		$this->shipping          = null;
		$this->tax               = null;
		$this->handling          = null;
		$this->insurance         = null;
		$this->shipping_discount = null;
		$this->custom_charges    = null;

		// Required field: total.
		if ( ! isset( $input['total'] ) || ! is_array( $input['total'] ) ) {
			$validation->add_missing_field( 'total', 'Please provide a total amount' );
		} else {
			$this->total = Money::from_array( $input['total'], $validation );
		}

		// Optional Money fields.
		$this->parse_optional_money_field( $input, 'subtotal', $validation );
		$this->parse_optional_money_field( $input, 'discount', $validation );
		$this->parse_optional_money_field( $input, 'shipping', $validation );
		$this->parse_optional_money_field( $input, 'tax', $validation );
		$this->parse_optional_money_field( $input, 'handling', $validation );
		$this->parse_optional_money_field( $input, 'insurance', $validation );
		$this->parse_optional_money_field( $input, 'shipping_discount', $validation );
		$this->parse_optional_money_field( $input, 'custom_charges', $validation );
	}

	private function parse_optional_money_field( array $input, string $field_name, StoreValidation $validation ): void {
		if ( isset( $input[ $field_name ] ) && is_array( $input[ $field_name ] ) ) {
			$this->$field_name = Money::from_array( $input[ $field_name ], $validation );
		}
	}

	public function total(): ?Money {
		return $this->total;
	}

	public function subtotal(): ?Money {
		return $this->subtotal;
	}

	public function discount(): ?Money {
		return $this->discount;
	}

	public function shipping(): ?Money {
		return $this->shipping;
	}

	public function tax(): ?Money {
		return $this->tax;
	}

	public function handling(): ?Money {
		return $this->handling;
	}

	public function insurance(): ?Money {
		return $this->insurance;
	}

	public function shipping_discount(): ?Money {
		return $this->shipping_discount;
	}

	public function custom_charges(): ?Money {
		return $this->custom_charges;
	}
}
