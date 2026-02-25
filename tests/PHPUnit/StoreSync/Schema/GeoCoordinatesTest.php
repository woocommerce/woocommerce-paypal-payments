<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Schema\GeoCoordinates
 */
class GeoCoordinatesTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return GeoCoordinates::class;
	}

	protected function get_valid_data(): array {
		return array(
			'latitude'     => '37.7749',
			'longitude'    => '-122.4194',
			'subdivision'  => 'CA',
			'country_code' => 'us',
		);
	}

	protected function get_expected_data(): array {
		return array(
			'latitude'     => 37.7749,
			'longitude'    => - 122.4194,
			'subdivision'  => 'CA',
			'country_code' => 'US',
		);
	}

	protected function get_data_types(): array {
		return array(
			'latitude'     => 'number',
			'longitude'    => 'number',
			'subdivision'  => 'string',
			'country_code' => 'country',
		);
	}

	public function test_required_fields(): void {
		// GeoCoordinates has no required fields - all fields are optional when schema is used.

		$this->assertOptionalField( 'latitude' );
		$this->assertOptionalField( 'longitude' );
		$this->assertOptionalField( 'subdivision' );
		$this->assertOptionalField( 'country_code' );
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'country_code', 'US' );
		$this->assertWhitespaceTrimming( 'subdivision', 'CA' );

		$this->assertStringFieldExactLength( 'country_code', 2 );
		$this->assertStringFieldMaxLength( 'subdivision', 10 );

		$this->assertFieldNormalizesToUppercase( 'country_code', 'us', 'US' );
		$this->assertFieldNormalizesToUppercase( 'subdivision', 'sample', 'SAMPLE' );
	}

	public function test_field_format_validation(): void {
		$this->assertFieldFormat( 'country_code', $this->get_country_code_format_cases() );
		$this->assertFieldFormat( 'subdivision', $this->get_geo_subdivision_test_cases() );
		$this->assertFieldFormat( 'latitude', $this->get_geo_latitude_test_cases() );
		$this->assertFieldFormat( 'longitude', $this->get_geo_longitude_test_cases() );
	}
}
