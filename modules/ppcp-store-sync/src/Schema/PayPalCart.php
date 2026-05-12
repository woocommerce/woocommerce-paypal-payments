<?php
/**
 * PayPal Cart, core (input) data.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;

class PayPalCart extends AgenticSchema {
	/**
	 * @var CartItem[] List of items in the cart.
	 */
	private array $items = array();

	private ?PaymentMethod $payment_method = null;

	private ?Customer $customer = null;

	private ?Address $shipping_address = null;

	private ?Address $billing_address = null;

	private ?GeoCoordinates $geo_coordinates = null;

	/**
	 * @var CheckoutField[]|null
	 */
	private ?array $checkout_fields = null;

	/**
	 * @var Coupon[]|null
	 */
	private ?array $coupons = null;

	/**
	 * @var ShippingOption[]|null
	 */
	private ?array $available_shipping_options = null;

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->items                      = array();
		$this->payment_method             = null;
		$this->customer                   = null;
		$this->shipping_address           = null;
		$this->billing_address            = null;
		$this->geo_coordinates            = null;
		$this->checkout_fields            = null;
		$this->coupons                    = null;
		$this->available_shipping_options = null;

		// Parse mandatory fields.
		if ( ! empty( $input['items'] ) && is_array( $input['items'] ) ) {
			$items = $input['items'];

			if ( count( $items ) > 100 ) {
				$add_issue(
					ValidationIssue::create_invalid_data( 'Too many items' )
						->user_message( 'The cart cannot hold more than 100 items' )
						->for_field( 'items' )
				);
			} else {
				foreach ( $items as $item ) {
					if ( is_object( $item ) ) {
						$item = (array) $item;
					}
					if ( ! is_array( $item ) ) {
						continue;
					}

					$this->items[] = CartItem::from_array( $item, $add_issue );
				}
			}
		} else {
			$add_issue(
				ValidationIssue::create_missing_field( 'Required field missing' )
					->user_message( 'Please provide a list of cart items.' )
					->for_field( 'items' )
			);
		}

		if ( ! empty( $input['payment_method'] ) && is_array( $input['payment_method'] ) ) {
			$this->payment_method =
				PaymentMethod::from_array( $input['payment_method'], $add_issue );
		} else {
			$add_issue(
				ValidationIssue::create_missing_field( 'Required field missing' )
					->user_message( 'No payment_method defined.' )
					->for_field( 'payment_method' )
			);
		}

		// Parse optional fields.
		if ( ! empty( $input['customer'] ) && is_array( $input['customer'] ) ) {
			$this->customer = Customer::from_array( $input['customer'], $add_issue );
		}

		if ( ! empty( $input['shipping_address'] ) && is_array( $input['shipping_address'] ) ) {
			$this->shipping_address = Address::from_array( $input['shipping_address'], $add_issue );
		}

		if ( ! empty( $input['billing_address'] ) && is_array( $input['billing_address'] ) ) {
			$this->billing_address = Address::from_array( $input['billing_address'], $add_issue );
		}

		if ( ! empty( $input['geo_coordinates'] ) && is_array( $input['geo_coordinates'] ) ) {
			$this->geo_coordinates =
				GeoCoordinates::from_array( $input['geo_coordinates'], $add_issue );
		}

		if ( isset( $input['checkout_fields'] ) && is_array( $input['checkout_fields'] ) ) {
			$checkout_fields       = $input['checkout_fields'];
			$this->checkout_fields = array();

			if ( count( $checkout_fields ) > 20 ) {
				$add_issue(
					ValidationIssue::create_invalid_data( 'Too many checkout fields' )
						->user_message( 'The cart cannot hold more than 20 checkout fields' )
						->for_field( 'checkout_fields' )
				);
			} else {
				foreach ( $checkout_fields as $field ) {
					$this->checkout_fields[] = CheckoutField::from_array( $field, $add_issue );
				}
			}
		}

		if ( isset( $input['coupons'] ) && is_array( $input['coupons'] ) ) {
			$this->coupons = array();

			foreach ( $input['coupons'] as $coupon ) {
				$this->coupons[] = Coupon::from_array( $coupon, $add_issue );
			}
		}

		if ( isset( $input['available_shipping_options'] ) && is_array( $input['available_shipping_options'] ) ) {
			$this->available_shipping_options = array();

			foreach ( $input['available_shipping_options'] as $option ) {
				$this->available_shipping_options[] = ShippingOption::from_array( $option, $add_issue );
			}
		}
	}

	public function items(): array {
		return $this->items;
	}

	public function payment_method(): ?PaymentMethod {
		return $this->payment_method;
	}

	public function customer(): ?Customer {
		return $this->customer;
	}

	public function shipping_address(): ?Address {
		return $this->shipping_address;
	}

	public function billing_address(): ?Address {
		return $this->billing_address;
	}

	public function geo_coordinates(): ?GeoCoordinates {
		return $this->geo_coordinates;
	}

	public function checkout_fields(): ?array {
		return $this->checkout_fields;
	}

	public function coupons(): ?array {
		return $this->coupons;
	}

	public function available_shipping_options(): ?array {
		return $this->available_shipping_options;
	}
}
