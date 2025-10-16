<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers Address
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
		$this->assertFieldFormat( 'country_code', $this->getCountryCodeFormatCases() );
	}
}
