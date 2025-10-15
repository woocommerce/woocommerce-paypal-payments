<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers Customer
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
		);
	}

	public function test_required_fields(): void {
		// Customer has no required fields - all fields are optional.
		$this->addToAssertionCount( 1 );
	}

	public function test_optional_fields(): void {
		$this->assertOptionalField( 'email_address' );
	}

	public function test_optional_customer_fields(): void {
		$data     = array();
		$customer = Customer::from_array( $data );

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
	}

	public function test_field_format_validation(): void {
		$this->assertFieldFormat( 'email_address', $this->getEmailAddressFormatCases() );
		$this->assertFieldFormat( 'phone.country_code', $this->getPhoneCountryCodeFormatCases() );
		$this->assertFieldFormat( 'phone.national_number', $this->getPhoneNationalNumberFormatCases() );
	}

	/**
	 * @dataProvider invalid_type_provider
	 */
	public function test_fields_reject_invalid_types( string $field_name, $invalid_value, string $getter_method, $expected_default ): void {
		$data     = array( $field_name => $invalid_value );
		$customer = Customer::from_array( $data );

		$this->assertSame( $expected_default, $customer->$getter_method() );
	}

	public function invalid_type_provider(): array {
		return array(
			'name with string'         => array( 'name', 'John Smith', 'name', null ),
			'name with integer'        => array( 'name', 123, 'name', null ),
			'email_address with array' => array(
				'email_address',
				array( 'email' ),
				'email_address',
				null,
			),
			'email_address with int'   => array( 'email_address', 123, 'email_address', null ),
			'phone with string'        => array( 'phone', '+1-555-1234', 'phone', null ),
			'phone with integer'       => array( 'phone', 5551234567, 'phone', null ),
		);
	}

	/**
	 * Tests that multiple validation errors are all returned together.
	 */
	public function test_multiple_validation_errors_returned_together(): void {
		$data = array(
			'name'          => array(
				'given_name' => str_repeat( 'a', 141 ),
				'surname'    => str_repeat( 'b', 141 ),
			),
			'email_address' => 'invalid-email',
			'phone'         => array(
				'country_code'    => '1234',
				'national_number' => '123456789012345',
			),
		);

		$customer = Customer::from_array( $data );
		$issues   = $customer->validate();

		$this->assertCount( 5, $issues, 'Should return all validation errors at once' );

		$fields = array_map(
			function ( $issue ) {
				return $issue->to_array()['field'];
			},
			$issues
		);

		$this->assertContains( 'name.given_name', $fields );
		$this->assertContains( 'name.surname', $fields );
		$this->assertContains( 'email_address', $fields );
		$this->assertContains( 'phone.country_code', $fields );
		$this->assertContains( 'phone.national_number', $fields );
	}

}
