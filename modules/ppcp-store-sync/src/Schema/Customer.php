<?php
/**
 * Defines the customer schema.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;

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

			if ( filter_var( $email_address, FILTER_VALIDATE_EMAIL ) ) {
				$this->email_address = $email_address;
			} else {
				$add_issue(
					ValidationIssue::create_invalid_data( 'Invalid email' )
						->user_message( 'The customers email address is not valid' )
						->for_field( 'email_address' )
				);
			}
		}
		if ( isset( $input['name'] ) && is_array( $input['name'] ) ) {
			$this->name = array(
				'given_name' => null,
				'surname'    => null,
			);

			$given_name = $input['name']['given_name'] ?? null;
			$surname    = $input['name']['surname'] ?? null;

			if ( is_string( $given_name ) ) {
				if ( strlen( $given_name ) > 140 ) {
					$add_issue(
						ValidationIssue::create_invalid_data( 'Given name too long' )
							->user_message( 'The customers given name cannot be longer than 140 characters' )
							->for_field( 'name.given_name' )
					);
				} else {
					$this->name['given_name'] = trim( $given_name );
				}
			}
			if ( is_string( $surname ) ) {
				if ( strlen( $surname ) > 140 ) {
					$add_issue(
						ValidationIssue::create_invalid_data( 'Surname too long' )
							->user_message( 'The customers surname cannot be longer than 140 characters' )
							->for_field( 'name.surname' )
					);
				} else {
					$this->name['surname'] = trim( $surname );
				}
			}
		}
		if ( isset( $input['phone'] ) && is_array( $input['phone'] ) ) {
			$this->phone = array(
				'country_code'    => null,
				'national_number' => null,
			);

			$country_code    = $input['phone']['country_code'] ?? null;
			$national_number = $input['phone']['national_number'] ?? null;

			if ( is_string( $country_code ) ) {
				$country_code = trim( $country_code );

				if ( ! is_numeric( $country_code ) || '0' === $country_code ) {
					$add_issue(
						ValidationIssue::create_invalid_data( 'Invalid country code format' )
							->user_message( 'The customers phone country-code must be numeric' )
							->for_field( 'phone.country_code' )
					);
				} elseif ( strlen( $country_code ) > 3 ) {
					$add_issue(
						ValidationIssue::create_invalid_data( 'Invalid country code length' )
							->user_message( 'The customers phone country-code must have between 1 and 3 digits' )
							->for_field( 'phone.country_code' )
					);
				} else {
					$this->phone['country_code'] = trim( $country_code );
				}
			}
			if ( is_string( $national_number ) ) {
				$national_number = trim( $national_number );

				if ( ! is_numeric( $national_number ) ) {
					$add_issue(
						ValidationIssue::create_invalid_data( 'Invalid national number format' )
							->user_message( 'The customers phone number must be numeric' )
							->for_field( 'phone.national_number' )
					);
				} elseif ( strlen( $national_number ) > 14 ) {
					$add_issue(
						ValidationIssue::create_invalid_data( 'Invalid national number length' )
							->user_message( 'The customers phone number must have between 1 and 3 digits' )
							->for_field( 'phone.national_number' )
					);
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
	 * @return null|array Customer name as an array, no own schema.
	 */
	public function name(): ?array {
		return $this->name;
	}

	/**
	 * @return null|array Phone number as an array, no own schema.
	 */
	public function phone(): ?array {
		return $this->phone;
	}
}
