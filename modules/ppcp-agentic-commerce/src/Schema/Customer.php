<?php
/**
 * Defines the customer schema.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidData;

/**
 * @see CustomerTest - Unit tests for this class.
 */
class Customer extends AgenticSchema {
	private ?string $email_address = null;

	private ?array $name = null;

	private ?array $phone = null;

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->email_address = null;
		$this->name          = null;
		$this->phone         = null;

		// Optional fields.
		if ( isset( $input['email_address'] ) && is_string( $input['email_address'] ) ) {
			$email_address = trim( $input['email_address'] );

			$this->email_address = $email_address;
			if ( strlen( $email_address ) > 254 ) {
				$add_issue( new InvalidData( 'Email address too long', 'The customers email address must be shorter than 255 characters', 'email_address' ) );
			} elseif ( ! filter_var( $email_address, FILTER_VALIDATE_EMAIL ) ) {
				$add_issue( new InvalidData( 'Invalid email', 'The customers email address is not valid', 'email_address' ) );
			}
		}
		if ( isset( $input['name'] ) && is_array( $input['name'] ) ) {
			$this->name = array();
			$given_name = $input['name']['given_name'] ?? null;
			$surname    = $input['name']['surname'] ?? null;

			if ( is_string( $given_name ) ) {
				if ( strlen( $given_name ) > 140 ) {
					$add_issue( new InvalidData( 'Given name too long', 'The customers given name cannot be longer than 140 characters', 'name.given_name' ) );
				} else {
					$this->name['given_name'] = trim( $given_name );
				}
			}
			if ( is_string( $surname ) ) {
				if ( strlen( $surname ) > 140 ) {
					$add_issue( new InvalidData( 'Surname too long', 'The customers surname cannot be longer than 140 characters', 'name.surname' ) );
				} else {
					$this->name['surname'] = trim( $surname );
				}
			}
		}
		if ( isset( $input['phone'] ) && is_array( $input['phone'] ) ) {
			$this->phone     = array();
			$country_code    = $input['phone']['country_code'] ?? null;
			$national_number = $input['phone']['national_number'] ?? null;

			if ( is_string( $country_code ) ) {
				if ( ! is_numeric( $country_code ) ) {
					$add_issue( new InvalidData( 'Invalid country code format', 'The customers phone country-code must be numeric', 'phone.country_code' ) );
				} elseif ( strlen( $country_code ) > 3 ) {
					$add_issue( new InvalidData( 'Invalid country code length', 'The customers phone country-code must have between 1 and 3 digits', 'phone.country_code' ) );
				} else {
					$this->phone['country_code'] = trim( $country_code );
				}
			}
			if ( is_string( $national_number ) ) {
				if ( ! is_numeric( $national_number ) ) {
					$add_issue( new InvalidData( 'Invalid national number format', 'The customers phone number must be numeric', 'phone.national_number' ) );
				} elseif ( strlen( $national_number ) > 14 ) {
					$add_issue( new InvalidData( 'Invalid national number length', 'The customers phone number must have between 1 and 3 digits', 'phone.national_number' ) );
				} else {
					$this->phone['national_number'] = trim( $national_number );
				}
			}
		}
	}

	public function email_address(): ?string {
		return $this->email_address;
	}

	/**
	 * @return null|array Customer name as array, no own schema.
	 */
	public function name(): ?array {
		return $this->name;
	}

	/**
	 * @return null|array Phone number as array, no own schema.
	 */
	public function phone(): ?array {
		return $this->phone;
	}
}
