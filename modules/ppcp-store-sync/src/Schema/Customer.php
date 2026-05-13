<?php
/**
 * Defines the customer schema.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;

/**
 * @see CustomerTest - Unit tests for this class.
 */
class Customer extends AgenticSchema {
	private ?string $email_address = null;

	private ?CustomerName $name = null;

	private ?CustomerPhone $phone = null;

	protected function parse_fields( array $input, StoreValidation $validation ): void {
		// Reset all fields.
		$this->email_address = null;
		$this->name          = null;
		$this->phone         = null;

		// Optional fields.
		if ( isset( $input['email_address'] ) && is_string( $input['email_address'] ) ) {
			$email_address = trim( $input['email_address'] );

			if ( filter_var( $email_address, FILTER_VALIDATE_EMAIL ) ) {
				$this->email_address = $email_address;
			} else {
				$validation->add_invalid_data(
					'email_address',
					'Invalid email',
					'The customers email address is not valid'
				);
			}
		}
		if ( isset( $input['name'] ) && is_array( $input['name'] ) ) {
			$this->name = CustomerName::from_array( $input['name'], $validation );
		}
		if ( isset( $input['phone'] ) && is_array( $input['phone'] ) ) {
			$this->phone = CustomerPhone::from_array( $input['phone'], $validation );
		}
	}

	public function email_address( ?string $default = null ): ?string {
		return $this->email_address ?? $default;
	}

	public function name( ?CustomerName $default = null ): ?CustomerName {
		return $this->name ?? $default;
	}

	public function phone( ?CustomerPhone $default = null ): ?CustomerPhone {
		return $this->phone ?? $default;
	}

	public function to_array(): array {
		$data = array(
			'email_address' => $this->email_address(),
			'name'          => $this->name ? $this->name->to_array() : null,
			'phone'         => $this->phone ? $this->phone->to_array() : null,
		);

		return array_filter( $data, static fn( $v ) => $v !== null );
	}

	public function full_name(): string {
		return $this->name ? $this->name->full_name() : '';
	}
}
