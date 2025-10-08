<?php
/**
 * Defines a postal address (shipping or billing).
 *
 * @see     https://github.com/paypal/agent-commerce/blob/28b799b0d11b6fb62f423e203de6ea4b9f2ce122/v1/docs/SCHEMA_REFERENCE.md#address
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidData;

/**
 * @see AddressTest - Unit tests for this class.
 */
class Address extends AgenticSchema {
	private string $country_code = '';

	private ?string $address_line_1 = null;

	private ?string $address_line_2 = null;

	private ?string $admin_area_2 = null;

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->country_code   = '';
		$this->address_line_1 = null;
		$this->address_line_2 = null;
		$this->admin_area_2   = null;

		// Parse mandatory fields.
		if ( isset( $input['country_code'] ) ) {
			$country_code = strtoupper( trim( $input['country_code'] ) );

			if ( 2 === strlen( $country_code ) ) {
				$this->country_code = $country_code;
			} else {
				$add_issue( new InvalidData( 'Unexpected country_code', 'Please provide a valid 2-letter country code.', 'country_code' ) );
			}
		} else {
			$add_issue( new InvalidData( 'Missing required field', 'Please provide a country code.', 'country_code' ) );
		}

		if ( isset( $input['address_line_1'] ) ) {
			$address_line_1 = trim( $input['address_line_1'] );

			if ( $address_line_1 && strlen( $address_line_1 ) <= 300 ) {
				$this->address_line_1 = $address_line_1;
			} else {
				$add_issue( new InvalidData( 'Field address_line_1 is too long', 'Please provide a valid address line 1.', 'address_line_1' ) );
			}
		}

		if ( isset( $input['address_line_2'] ) ) {
			$address_line_2 = trim( $input['address_line_2'] );

			if ( $address_line_2 && strlen( $address_line_2 ) <= 300 ) {
				$this->address_line_2 = $address_line_2;
			} else {
				$add_issue( new InvalidData( 'Field address_line_2 is too long', 'Please provide a valid address line 2.', 'address_line_2' ) );
			}
		}

		if ( isset( $input['admin_area_2'] ) ) {
			$admin_area_2 = trim( $input['admin_area_2'] );

			if ( $admin_area_2 && strlen( $admin_area_2 ) <= 120 ) {
				$this->admin_area_2 = $admin_area_2;
			} else {
				$add_issue( new InvalidData( 'Field admin_area_2 is too long', 'Please provide a valid admin area 2.', 'admin_area_2' ) );
			}
		}
	}

	public function country_code(): string {
		return $this->country_code;
	}

	public function address_line_1(): ?string {
		return $this->address_line_1;
	}

	public function address_line_2(): ?string {
		return $this->address_line_2;
	}

	public function admin_area_2(): ?string {
		return $this->admin_area_2;
	}
}
