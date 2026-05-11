<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Schema\Address
 */
class AddressTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return Address::class;
	}

	protected function get_valid_data(): array {
		return array(
			'address_line_1' => '123 Main Street',
			'address_line_2' => 'Apt 4B',
			'admin_area_2'   => 'San Jose',
			'admin_area_1'   => 'CA',
			'postal_code'    => '95131',
			'country_code'   => 'us',
		);
	}

	protected function get_expected_data(): array {
		return array(
			'address_line_1' => '123 Main Street',
			'address_line_2' => 'Apt 4B',
			'admin_area_2'   => 'San Jose',
			'admin_area_1'   => 'CA',
			'postal_code'    => '95131',
			'country_code'   => 'US',
		);
	}

	protected function get_data_types(): array {
		return array(
			'address_line_1' => 'string',
			'address_line_2' => 'string',
			'admin_area_2'   => 'string',
			'admin_area_1'   => 'string',
			'postal_code'    => 'string',
			'country_code'   => 'country',
		);
	}

	protected function mandatory_data(): array {
		return array( 'country_code' => 'US' );
	}

	public function test_required_fields(): void {
		$this->assertRequiredField( 'country_code' );

		$this->assertOptionalField( 'address_line_1' );
		$this->assertOptionalField( 'address_line_2' );
		$this->assertOptionalField( 'admin_area_2' );
		$this->assertOptionalField( 'admin_area_1' );
		$this->assertOptionalField( 'postal_code' );
	}

	public function test_string_fields(): void {
		$this->assertEmptyStringPreserved( 'address_line_1' );
		$this->assertEmptyStringPreserved( 'address_line_2' );
		$this->assertEmptyStringPreserved( 'admin_area_2' );
		$this->assertEmptyStringPreserved( 'admin_area_1' );
		$this->assertEmptyStringPreserved( 'postal_code' );

		$this->assertWhitespaceTrimming( 'country_code', 'US' );
		$this->assertWhitespaceTrimming( 'address_line_1', 'ABC' );
		$this->assertWhitespaceTrimming( 'address_line_2', 'ABC' );
		$this->assertWhitespaceTrimming( 'admin_area_2', 'ABC' );
		$this->assertWhitespaceTrimming( 'admin_area_1', 'ABC' );
		$this->assertWhitespaceTrimming( 'postal_code', 'ABC' );

		$this->assertStringFieldExactLength( 'country_code', 2 );
		$this->assertStringFieldMaxLength( 'address_line_1', 300 );
		$this->assertStringFieldMaxLength( 'address_line_2', 300 );
		$this->assertStringFieldMaxLength( 'admin_area_2', 120 );
		$this->assertStringFieldMaxLength( 'admin_area_1', 300 );
		$this->assertStringFieldMaxLength( 'postal_code', 60 );

		$this->assertFieldNormalizesToUppercase( 'country_code', 'us', 'US' );
		$this->assertFieldIsCaseSensitive( 'address_line_1', 'sample' );
		$this->assertFieldIsCaseSensitive( 'address_line_2', 'sample' );
		$this->assertFieldIsCaseSensitive( 'admin_area_2', 'sample' );
		$this->assertFieldIsCaseSensitive( 'admin_area_1', 'sample' );
		$this->assertFieldIsCaseSensitive( 'postal_code', 'sample' );

		$this->assertFieldAcceptsSpecialCharacters( 'address_line_1' );
		$this->assertFieldAcceptsSpecialCharacters( 'address_line_2' );
		$this->assertFieldAcceptsSpecialCharacters( 'admin_area_2' );
		$this->assertFieldAcceptsSpecialCharacters( 'admin_area_1' );
		$this->assertFieldAcceptsSpecialCharacters( 'postal_code' );
	}

	public function test_field_format_validation(): void {
		$this->assertFieldFormat( 'country_code', $this->get_country_code_format_cases() );
	}

	// -------------------------------------------------------------------------
	// Group — to_array()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN an Address with all six fields populated
	 * WHEN to_array() is called
	 * THEN all six fields appear in the result with the correct sanitized values
	 * AND country_code is normalized to uppercase
	 */
	public function test_to_array_returns_all_six_fields_with_sanitized_values(): void {
		$address = Address::from_array(
			array(
				'address_line_1' => '123 Main Street',
				'address_line_2' => 'Apt 4B',
				'admin_area_2'   => 'San Jose',
				'admin_area_1'   => 'CA',
				'postal_code'    => '95131',
				'country_code'   => 'us',
			),
			new StoreValidation()
		);

		$result = $address->to_array();

		$this->assertSame( '123 Main Street', $result['address_line_1'] );
		$this->assertSame( 'Apt 4B', $result['address_line_2'] );
		$this->assertSame( 'San Jose', $result['admin_area_2'] );
		$this->assertSame( 'CA', $result['admin_area_1'] );
		$this->assertSame( '95131', $result['postal_code'] );
		$this->assertSame( 'US', $result['country_code'] );
	}

	/**
	 * GIVEN an Address with only the mandatory country_code field provided
	 * WHEN to_array() is called
	 * THEN all six keys are present and optional fields are empty strings
	 */
	public function test_to_array_returns_empty_strings_for_missing_optional_fields(): void {
		$address = Address::from_array(
			array( 'country_code' => 'DE' ),
			new StoreValidation()
		);

		$result = $address->to_array();

		$this->assertArrayHasKey( 'address_line_1', $result );
		$this->assertArrayHasKey( 'address_line_2', $result );
		$this->assertArrayHasKey( 'admin_area_2', $result );
		$this->assertArrayHasKey( 'admin_area_1', $result );
		$this->assertArrayHasKey( 'postal_code', $result );
		$this->assertSame( '', $result['address_line_1'] );
		$this->assertSame( '', $result['address_line_2'] );
		$this->assertSame( '', $result['admin_area_2'] );
		$this->assertSame( '', $result['admin_area_1'] );
		$this->assertSame( '', $result['postal_code'] );
		$this->assertSame( 'DE', $result['country_code'] );
	}

	// -------------------------------------------------------------------------
	// Group — empty_array()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN no Address instance (static context)
	 * WHEN Address::empty_array() is called
	 * THEN an array with all six keys is returned and every value is an empty string
	 */
	public function test_empty_array_returns_all_six_keys_as_empty_strings(): void {
		$result = Address::empty_array();

		$expected_keys = array( 'address_line_1', 'address_line_2', 'admin_area_2', 'admin_area_1', 'postal_code', 'country_code' );
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $result, "Key '$key' must be present in empty_array() result" );
			$this->assertSame( '', $result[ $key ], "Key '$key' must be an empty string in empty_array() result" );
		}
	}
}
