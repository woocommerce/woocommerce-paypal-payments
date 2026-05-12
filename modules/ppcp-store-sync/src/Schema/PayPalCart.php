<?php
/**
 * PayPal Cart, core (input) data.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;

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

	protected function parse_fields( array $input, StoreValidation $validation ): void {
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
				$validation->add_invalid_data(
					'items',
					'Too many items',
					'The cart cannot hold more than 100 items'
				);
			} else {
				foreach ( $items as $item ) {
					if ( is_object( $item ) ) {
						$item = (array) $item;
					}
					if ( ! is_array( $item ) ) {
						continue;
					}

					$this->items[] = CartItem::from_array( $item, $validation );
				}
			}
		} else {
			$validation->add_missing_field( 'items', 'Please provide a list of cart items.' );
		}

		if ( ! empty( $input['payment_method'] ) && is_array( $input['payment_method'] ) ) {
			$this->payment_method = PaymentMethod::from_array(
				$input['payment_method'],
				$validation
			);
		} else {
			$validation->add_missing_field( 'payment_method', 'No payment_method defined.' );
		}

		// Parse optional fields.
		if ( ! empty( $input['customer'] ) && is_array( $input['customer'] ) ) {
			$this->customer = Customer::from_array( $input['customer'], $validation );
		}

		if ( ! empty( $input['shipping_address'] ) && is_array( $input['shipping_address'] ) ) {
			$this->shipping_address = Address::from_array(
				$input['shipping_address'],
				$validation
			);
		}

		if ( ! empty( $input['billing_address'] ) && is_array( $input['billing_address'] ) ) {
			$this->billing_address = Address::from_array(
				$input['billing_address'],
				$validation
			);
		}

		if ( ! empty( $input['geo_coordinates'] ) && is_array( $input['geo_coordinates'] ) ) {
			$this->geo_coordinates = GeoCoordinates::from_array(
				$input['geo_coordinates'],
				$validation
			);
		}

		if ( isset( $input['checkout_fields'] ) && is_array( $input['checkout_fields'] ) ) {
			$checkout_fields       = $input['checkout_fields'];
			$this->checkout_fields = array();

			if ( count( $checkout_fields ) > 20 ) {
				$validation->add_invalid_data(
					'checkout_fields',
					'Too many checkout fields',
					'The cart cannot hold more than 20 checkout fields'
				);
			} else {
				foreach ( $checkout_fields as $field ) {
					$this->checkout_fields[] = CheckoutField::from_array( $field, $validation );
				}
			}
		}

		if ( isset( $input['coupons'] ) && is_array( $input['coupons'] ) ) {
			$this->coupons = array();

			foreach ( $input['coupons'] as $coupon ) {
				$this->coupons[] = Coupon::from_array( $coupon, $validation );
			}
		}

		if ( isset( $input['available_shipping_options'] ) && is_array( $input['available_shipping_options'] ) ) {
			$this->available_shipping_options = array();

			foreach ( $input['available_shipping_options'] as $option ) {
				$this->available_shipping_options[] = ShippingOption::from_array(
					$option,
					$validation
				);
			}
		}
	}

	/**
	 * Returns a sanitized representation of the cart for session/order-manager persistence.
	 * Only fields with schema-owned serialization are included; geo_coordinates, checkout_fields,
	 * coupons, and available_shipping_options are intentionally omitted until their schemas gain
	 * to_array() methods.
	 */
	public function to_array(): array {
		$data = array(
			'items'            => array_map( static fn( CartItem $item ) => $item->to_array(), $this->items ),
			'payment_method'   => $this->payment_method()->to_array(),
			'customer'         => $this->customer ? $this->customer->to_array() : null,
			'shipping_address' => $this->shipping_address()->to_array(),
			'billing_address'  => $this->billing_address ? $this->billing_address->to_array() : null,
		);

		return array_filter( $data, static fn( $v ) => $v !== null );
	}

	public function items(): array {
		return $this->items;
	}

	public function payment_method(): PaymentMethod {
		if ( ! $this->payment_method ) {
			$this->payment_method = PaymentMethod::create_empty();
		}

		return $this->payment_method;
	}

	public function customer(): ?Customer {
		return $this->customer;
	}

	public function shipping_address(): Address {
		if ( ! $this->shipping_address ) {
			$this->shipping_address = Address::create_empty();
		}

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
