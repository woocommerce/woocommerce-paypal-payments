<?php
/**
 * PayPal Cart, core (input) data.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\MissingField;

class PayPalCart extends AgenticSchema {
	/**
	 * @var CartItem[] List of items in the cart.
	 */
	private array $items = array();

	/**
	 * @var array Payment method data; must define the type "paypal".
	 */
	private array $payment_method = array();

	private ?array $customer = null;

	private ?array $shipping_address = null;

	private ?array $checkout_fields = null;

	private ?array $coupons = null;

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->items            = array();
		$this->payment_method   = array();
		$this->customer         = null;
		$this->shipping_address = null;
		$this->checkout_fields  = null;
		$this->coupons          = null;

		// Parse mandatory fields.
		if ( ! empty( $input['items'] ) && is_array( $input['items'] ) ) {
			foreach ( $input['items'] as $item ) {
				$this->items[] = CartItem::from_array( $item, $add_issue );
			}
		} else {
			/** @psalm-suppress MissingThrowsDocblock -- Errors mean the class-code is invalid */
			$add_issue( new MissingField( 'Required field missing', 'Please provide a list of cart items.', 'items' ) );
		}

		if ( ! empty( $input['payment_method'] ) && is_array( $input['payment_method'] ) ) {
			$this->payment_method = $input['payment_method'];
		} else {
			/** @psalm-suppress MissingThrowsDocblock -- Errors mean the class-code is invalid */
			$add_issue( new MissingField( 'Required field missing', 'No payment_method defined.', 'payment_method' ) );
		}

		// Parse optional fields.
		if ( ! empty( $input['customer'] ) && is_array( $input['customer'] ) ) {
			$this->customer = $input['customer'];
		}

		if ( ! empty( $input['shipping_address'] ) && is_array( $input['shipping_address'] ) ) {
			$this->shipping_address = $input['shipping_address'];
		}

		if ( ! empty( $input['checkout_fields'] ) && is_array( $input['checkout_fields'] ) ) {
			$this->checkout_fields = $input['checkout_fields'];
		}

		if ( ! empty( $input['coupons'] ) && is_array( $input['coupons'] ) ) {
			$this->coupons = $input['coupons'];
		}
	}

	public function items(): array {
		return $this->items;
	}

	public function payment_method(): array {
		return $this->payment_method;
	}

	public function customer(): ?array {
		return $this->customer;
	}

	public function shipping_address(): ?array {
		return $this->shipping_address;
	}

	public function checkout_fields(): ?array {
		return $this->checkout_fields;
	}

	public function coupons(): ?array {
		return $this->coupons;
	}
}
