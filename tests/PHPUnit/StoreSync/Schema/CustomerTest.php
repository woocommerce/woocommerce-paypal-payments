<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Schema\Customer
 */
class CustomerTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return Customer::class;
	}

	protected function get_valid_data(): array {
		return array(
			'name'          => array(
				'given_name' => 'John',
				'surname'    => 'Smith',
			),
			'email_address' => 'john.smith@example.com',
			'phone'         => array(
				'country_code'    => '1',
				'national_number' => '5551234567',
			),
		);
	}

	protected function get_expected_data(): array {
		return array(
			'name.given_name'       => 'John',
			'name.surname'          => 'Smith',
			'email_address'         => 'john.smith@example.com',
			'phone.country_code'    => '1',
			'phone.national_number' => '5551234567',
		);
	}

	protected function get_data_types(): array {
		return array(
			'email_address' => 'email',
			'name'          => array( 'type' => 'array', 'valid' => array() ),
			'phone'         => array( 'type' => 'array', 'valid' => array() ),
		);
	}

	public function test_required_fields(): void {
		// Customer has no required fields - all fields are optional.

		$this->assertOptionalField( 'email_address' );

		// Test optional nested fields.
		$customer = Customer::from_array( array(), new StoreValidation() );

		$this->assertNull( $customer->name() );
		$this->assertNull( $customer->phone() );
	}

	public function test_string_fields(): void {
		// Top-level optional fields
		$this->assertWhitespaceTrimming( 'email_address', 'test@example.com' );

		// Nested name fields
		$this->assertWhitespaceTrimming( 'name.given_name', 'John' );
		$this->assertWhitespaceTrimming( 'name.surname', 'Smith' );
		$this->assertEmptyStringPreserved( 'name.given_name' );
		$this->assertEmptyStringPreserved( 'name.surname' );
		$this->assertStringFieldMaxLength( 'name.given_name', 140 );
		$this->assertStringFieldMaxLength( 'name.surname', 140 );

		// Nested phone fields (digits only, no max length test for pattern-validated fields)
		$this->assertWhitespaceTrimming( 'phone.country_code', '1' );
		$this->assertWhitespaceTrimming( 'phone.national_number', '5551234567' );

		$this->assertFieldIsCaseSensitive( 'name.given_name', 'john' );
		$this->assertFieldIsCaseSensitive( 'name.surname', 'smith' );
		$this->assertFieldIsCaseSensitive( 'email_address', 'john.smith@example.com' );

		$this->assertFieldAcceptsSpecialCharacters( 'name.given_name' );
		$this->assertFieldAcceptsSpecialCharacters( 'name.surname' );
	}

	public function test_field_format_validation(): void {
		$this->assertFieldFormat( 'email_address', $this->get_email_address_format_cases() );
		$this->assertFieldFormat( 'phone.country_code', $this->get_phone_country_code_format_cases() );
		$this->assertFieldFormat( 'phone.national_number', $this->get_phone_national_number_format_cases() );
	}
}
