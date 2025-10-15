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

	public function test_required_fields(): void {
		// Customer has no required fields - all fields are optional.
		$this->addToAssertionCount( 1 );
	}

	public function test_optional_fields(): void {
		$this->assertOptionalField( 'email_address' );

		$this->assertFieldReturnsType( array(
			'name' => array(
				'given_name' => 'John',
				'surname'    => 'Smith',
			),
		), 'name', 'array' );
		$this->assertFieldReturnsType( array(
			'phone' => array(
				'country_code'    => '1',
				'national_number' => '5551234567',
			),
		), 'phone', 'array' );
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
		$this->assertEmptyStringPreserved( 'email_address', 'email_address' );

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

	/**
	 * Tests that Customer stores and returns the name object.
	 */
	public function test_name_accessor(): void {
		$data = array(
			'name' => array(
				'given_name' => 'John',
				'surname'    => 'Smith',
			),
		);

		$customer = Customer::from_array( $data );
		$name     = $customer->name();

		$this->assertIsArray( $name );
		$this->assertSame( 'John', $name['given_name'] );
		$this->assertSame( 'Smith', $name['surname'] );
	}

	/**
	 * Tests that Customer stores and returns the email_address.
	 */
	public function test_email_address_accessor(): void {
		$data     = array( 'email_address' => 'john.smith@example.com' );
		$customer = Customer::from_array( $data );

		$this->assertSame( 'john.smith@example.com', $customer->email_address() );
	}

	/**
	 * Tests that Customer stores and returns the phone object.
	 */
	public function test_phone_accessor(): void {
		$data = array(
			'phone' => array(
				'country_code'    => '1',
				'national_number' => '5551234567',
			),
		);

		$customer = Customer::from_array( $data );
		$phone    = $customer->phone();

		$this->assertIsArray( $phone );
		$this->assertSame( '1', $phone['country_code'] );
		$this->assertSame( '5551234567', $phone['national_number'] );
	}

	/**
	 * @dataProvider optional_field_provider
	 */
	public function test_optional_fields_return_null_when_missing( string $field_name, string $getter_method ): void {
		$data     = array();
		$customer = Customer::from_array( $data );

		$this->assertNull( $customer->$getter_method() );
	}

	public function optional_field_provider(): array {
		return array(
			'name'          => array( 'name', 'name' ),
			'email_address' => array( 'email_address', 'email_address' ),
			'phone'         => array( 'phone', 'phone' ),
		);
	}

	/**
	 * Tests that given_name exceeding 140 characters produces validation issue.
	 */
	public function test_given_name_exceeds_max_length(): void {
		$data = array(
			'name' => array(
				'given_name' => str_repeat( 'a', 141 ),
			),
		);

		$customer   = Customer::from_array( $data );
		$issues     = $customer->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'name.given_name', $issue_data['field'] );
	}

	/**
	 * Tests that given_name with exactly 140 characters is valid.
	 */
	public function test_given_name_at_max_length_is_valid(): void {
		$data = array(
			'name' => array(
				'given_name' => str_repeat( 'a', 140 ),
			),
		);

		$customer = Customer::from_array( $data );
		$issues   = $customer->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that surname exceeding 140 characters produces validation issue.
	 */
	public function test_surname_exceeds_max_length(): void {
		$data = array(
			'name' => array(
				'surname' => str_repeat( 'a', 141 ),
			),
		);

		$customer   = Customer::from_array( $data );
		$issues     = $customer->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'name.surname', $issue_data['field'] );
	}

	/**
	 * Tests that surname with exactly 140 characters is valid.
	 */
	public function test_surname_at_max_length_is_valid(): void {
		$data = array(
			'name' => array(
				'surname' => str_repeat( 'a', 140 ),
			),
		);

		$customer = Customer::from_array( $data );
		$issues   = $customer->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that email_address exceeding 254 characters produces validation issue.
	 */
	public function test_email_address_exceeds_max_length(): void {
		$long_email = str_repeat( 'a', 245 ) . '@test.com'; // 254+ chars

		$data = array(
			'email_address' => $long_email,
		);

		$customer   = Customer::from_array( $data );
		$issues     = $customer->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'email_address', $issue_data['field'] );
	}

	/**
	 * Tests that invalid email format produces validation issue.
	 */
	public function test_email_address_invalid_format(): void {
		$data = array(
			'email_address' => 'not-an-email',
		);

		$customer   = Customer::from_array( $data );
		$issues     = $customer->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'email_address', $issue_data['field'] );
	}

	/**
	 * @dataProvider valid_email_provider
	 */
	public function test_valid_email_formats_accepted( string $email ): void {
		$data = array(
			'email_address' => $email,
		);

		$customer = Customer::from_array( $data );
		$issues   = $customer->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( $email, $customer->email_address() );
	}

	public function valid_email_provider(): array {
		return array(
			'simple email'     => array( 'test@example.com' ),
			'with plus'        => array( 'user+tag@example.com' ),
			'with subdomain'   => array( 'user@mail.example.com' ),
			'with dots'        => array( 'first.last@example.com' ),
			'with numbers'     => array( 'user123@example.com' ),
			'with hyphens'     => array( 'user-name@ex-ample.com' ),
			'short domain'     => array( 'a@b.co' ),
			'long valid email' => array( str_repeat( 'a', 64 ) . '@' . str_repeat( 'b', 63 ) . '.' . str_repeat( 'c', 63 ) . '.' . str_repeat( 'd', 61 ) ),
		);
	}

	/**
	 * Tests that country_code exceeding 3 digits produces validation issue.
	 */
	public function test_country_code_exceeds_max_length(): void {
		$data = array(
			'phone' => array(
				'country_code'    => '1234',
				'national_number' => '5551234567',
			),
		);

		$customer   = Customer::from_array( $data );
		$issues     = $customer->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'phone.country_code', $issue_data['field'] );
	}

	/**
	 * Tests that country_code with non-digit characters produces validation issue.
	 */
	public function test_country_code_invalid_format(): void {
		$data = array(
			'phone' => array(
				'country_code'    => '1a',
				'national_number' => '5551234567',
			),
		);

		$customer   = Customer::from_array( $data );
		$issues     = $customer->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'phone.country_code', $issue_data['field'] );
	}

	/**
	 * @dataProvider valid_country_code_provider
	 */
	public function test_valid_country_codes_accepted( string $country_code ): void {
		$data = array(
			'phone' => array(
				'country_code'    => $country_code,
				'national_number' => '5551234567',
			),
		);

		$customer = Customer::from_array( $data );
		$issues   = $customer->validate();

		$this->assertEmpty( $issues );
	}

	public function valid_country_code_provider(): array {
		return array(
			'1 digit (US)'      => array( '1' ),
			'2 digits (UK)'     => array( '44' ),
			'3 digits (Russia)' => array( '380' ),
		);
	}

	/**
	 * Tests that national_number exceeding 14 digits produces validation issue.
	 */
	public function test_national_number_exceeds_max_length(): void {
		$data = array(
			'phone' => array(
				'country_code'    => '1',
				'national_number' => '123456789012345', // 15 digits
			),
		);

		$customer   = Customer::from_array( $data );
		$issues     = $customer->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'phone.national_number', $issue_data['field'] );
	}

	/**
	 * Tests that national_number with non-digit characters produces validation issue.
	 */
	public function test_national_number_invalid_format(): void {
		$data = array(
			'phone' => array(
				'country_code'    => '1',
				'national_number' => '555-123-4567',
			),
		);

		$customer   = Customer::from_array( $data );
		$issues     = $customer->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'phone.national_number', $issue_data['field'] );
	}

	/**
	 * Tests that national_number with exactly 14 digits is valid.
	 */
	public function test_national_number_at_max_length_is_valid(): void {
		$data = array(
			'phone' => array(
				'country_code'    => '1',
				'national_number' => '12345678901234', // 14 digits
			),
		);

		$customer = Customer::from_array( $data );
		$issues   = $customer->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that national_number with single digit is valid.
	 */
	public function test_national_number_single_digit_is_valid(): void {
		$data = array(
			'phone' => array(
				'country_code'    => '1',
				'national_number' => '5',
			),
		);

		$customer = Customer::from_array( $data );
		$issues   = $customer->validate();

		$this->assertEmpty( $issues );
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
	 * Tests that empty string for email_address is preserved.
	 */
	public function test_empty_email_address_is_preserved(): void {
		$data     = array( 'email_address' => '' );
		$customer = Customer::from_array( $data );

		$this->assertSame( '', $customer->email_address() );
	}

	/**
	 * Tests that name with missing given_name is stored without validation error.
	 */
	public function test_name_with_missing_given_name(): void {
		$data = array(
			'name' => array(
				'surname' => 'Smith',
			),
		);

		$customer = Customer::from_array( $data );
		$name     = $customer->name();
		$issues   = $customer->validate();

		$this->assertIsArray( $name );
		$this->assertNull( $name['given_name'] );
		$this->assertSame( 'Smith', $name['surname'] );
		$this->assertEmpty( $issues, 'Missing given_name should not produce validation error' );
	}

	/**
	 * Tests that name with missing surname is stored without validation error.
	 */
	public function test_name_with_missing_surname(): void {
		$data = array(
			'name' => array(
				'given_name' => 'John',
			),
		);

		$customer = Customer::from_array( $data );
		$name     = $customer->name();
		$issues   = $customer->validate();

		$this->assertIsArray( $name );
		$this->assertSame( 'John', $name['given_name'] );
		$this->assertNull( $name['surname'] );
		$this->assertEmpty( $issues, 'Missing surname should not produce validation error' );
	}

	/**
	 * Tests that phone with missing country_code is stored without validation error.
	 */
	public function test_phone_with_missing_country_code(): void {
		$data = array(
			'phone' => array(
				'national_number' => '5551234567',
			),
		);

		$customer = Customer::from_array( $data );
		$phone    = $customer->phone();
		$issues   = $customer->validate();

		$this->assertIsArray( $phone );
		$this->assertNull( $phone['country_code'] );
		$this->assertSame( '5551234567', $phone['national_number'] );
		$this->assertEmpty( $issues, 'Missing country_code should not produce validation error' );
	}

	/**
	 * Tests that phone with missing national_number is stored without validation error.
	 */
	public function test_phone_with_missing_national_number(): void {
		$data = array(
			'phone' => array(
				'country_code' => '1',
			),
		);

		$customer = Customer::from_array( $data );
		$phone    = $customer->phone();
		$issues   = $customer->validate();

		$this->assertIsArray( $phone );
		$this->assertSame( '1', $phone['country_code'] );
		$this->assertNull( $phone['national_number'] );
		$this->assertEmpty( $issues, 'Missing national_number should not produce validation error' );
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

	/**
	 * Tests that whitespace-only email_address is trimmed to empty string.
	 */
	public function test_whitespace_only_email_trimmed_to_empty(): void {
		$data     = array( 'email_address' => '   ' );
		$customer = Customer::from_array( $data );

		$this->assertSame( '', $customer->email_address() );
	}

	/**
	 * Tests that leading/trailing whitespace is trimmed from string values.
	 *
	 * @dataProvider whitespace_trimming_provider
	 */
	public function test_string_values_are_trimmed( array $data, string $field_path, string $expected_value ): void {
		$customer = Customer::from_array( $data );

		if ( str_contains( $field_path, '.' ) ) {
			list( $parent, $child ) = explode( '.', $field_path );
			$parent_value = $customer->$parent();
			$this->assertSame( $expected_value, $parent_value[ $child ] );
		} else {
			$this->assertSame( $expected_value, $customer->$field_path() );
		}
	}

	public function whitespace_trimming_provider(): array {
		return array(
			'given_name with leading space'  => array(
				array(
					'name' => array(
						'given_name' => ' John',
					),
				),
				'name.given_name',
				'John',
			),
			'given_name with trailing space' => array(
				array(
					'name' => array(
						'given_name' => 'John ',
					),
				),
				'name.given_name',
				'John',
			),
			'surname with both spaces'       => array(
				array(
					'name' => array(
						'surname' => '  Smith  ',
					),
				),
				'name.surname',
				'Smith',
			),
			'email_address with spaces'      => array(
				array( 'email_address' => ' john@example.com ' ),
				'email_address',
				'john@example.com',
			),
			'country_code with spaces'       => array(
				array(
					'phone' => array(
						'country_code' => ' 1 ',
					),
				),
				'phone.country_code',
				'1',
			),
			'national_number with spaces'    => array(
				array(
					'phone' => array(
						'national_number' => ' 5551234567 ',
					),
				),
				'phone.national_number',
				'5551234567',
			),
		);
	}

	/**
	 * Tests that empty name object with both fields missing is stored.
	 */
	public function test_empty_name_object_is_stored(): void {
		$data = array(
			'name' => array(),
		);

		$customer = Customer::from_array( $data );
		$name     = $customer->name();

		$this->assertIsArray( $name );
		$this->assertNull( $name['given_name'] );
		$this->assertNull( $name['surname'] );
	}

	/**
	 * Tests that empty phone object with both fields missing is stored.
	 */
	public function test_empty_phone_object_is_stored(): void {
		$data = array(
			'phone' => array(),
		);

		$customer = Customer::from_array( $data );
		$phone    = $customer->phone();

		$this->assertIsArray( $phone );
		$this->assertNull( $phone['country_code'] );
		$this->assertNull( $phone['national_number'] );
	}

	/**
	 * Tests that name fields with empty strings are preserved.
	 */
	public function test_name_empty_strings_are_preserved(): void {
		$data = array(
			'name' => array(
				'given_name' => '',
				'surname'    => '',
			),
		);

		$customer = Customer::from_array( $data );
		$name     = $customer->name();

		$this->assertSame( '', $name['given_name'] );
		$this->assertSame( '', $name['surname'] );
	}
}
