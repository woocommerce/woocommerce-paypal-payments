<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers GeoCoordinates
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
			'country_code' => 'US',
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

	public function test_required_fields(): void {
		// GeoCoordinates has no required fields - all fields are optional when schema is used.
		$this->addToAssertionCount( 1 );
	}

	public function test_optional_fields(): void {
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
	}

	public function test_field_format_validation(): void {
		$this->assertFieldFormat( 'country_code', $this->getCountryCodeFormatCases() );
		$this->assertFieldFormat( 'subdivision', $this->get_subdivision_values() );
		$this->assertFieldFormat( 'latitude', $this->get_latitude_values() );
		$this->assertFieldFormat( 'longitude', $this->get_longitude_values() );
	}

	public function get_latitude_values(): array {
		return array(
			'zero'                 => array( '0', true, 0. ),
			'positive integer'     => array( '45', true, 45. ),
			'negative integer'     => array( '-45', true, - 45. ),
			'positive decimal'     => array( '37.7749', true, 37.7749 ),
			'negative decimal'     => array( '-33.8688', true, - 33.8688 ),
			'max positive'         => array( '90', true, 90. ),
			'leading space'        => array( '  90', true, 90. ),
			'trailing space'       => array( '90  ', true, 90. ),
			'max positive decimal' => array( '90.0', true, 90.0 ),
			'max negative'         => array( '-90', true, - 90. ),
			'max negative decimal' => array( '-90.0', true, - 90.0 ),
			'small positive'       => array( '0.0001', true, 0.0001 ),
			'small negative'       => array( '-0.0001', true, - 0.0001 ),
			'single digit'         => array( '5', true, 5. ),
			'89.9999'              => array( '89.9999', true, 89.9999 ),
			'int'                  => array( 23, true, 23. ),
			'float'                => array( 23.0, true, 23. ),
			'exceeds max'          => array( '90.1', false ),
			'exceeds min'          => array( '-90.1', false ),
			'way too large'        => array( '180', false ),
			'way too small'        => array( '-180', false ),
			'non-numeric'          => array( 'abc', false ),
			'with units'           => array( '45°', false ),
			'with comma'           => array( '45,5', false ),
			'multiple decimals'    => array( '45.5.5', false ),
		);
	}

	public function get_longitude_values(): array {
		return array(
			'zero'                 => array( '0', true, 0. ),
			'positive integer'     => array( '90', true, 90. ),
			'negative integer'     => array( '-90', true, - 90. ),
			'positive decimal'     => array( '122.4194', true, 122.4194 ),
			'negative decimal'     => array( '-122.4194', true, - 122.4194 ),
			'max positive'         => array( '180', true, 180. ),
			'leading space'        => array( '  180', true, 180. ),
			'trailing space'       => array( '180  ', true, 180. ),
			'max positive decimal' => array( '180.0', true, 180.0 ),
			'max negative'         => array( '-180', true, - 180. ),
			'max negative decimal' => array( '-180.0', true, - 180.0 ),
			'small positive'       => array( '0.0001', true, 0.0001 ),
			'small negative'       => array( '-0.0001', true, - 0.0001 ),
			'single digit'         => array( '5', true, 5. ),
			'179.9999'             => array( '179.9999', true, 179.9999 ),
			'int'                  => array( 23, true, 23. ),
			'float'                => array( 23.0, true, 23. ),
			'exceeds max'          => array( '180.1', false ),
			'exceeds min'          => array( '-180.1', false ),
			'way too large'        => array( '360', false ),
			'way too small'        => array( '-360', false ),
			'non-numeric'          => array( 'xyz', false ),
			'with units'           => array( '-122°', false ),
			'with comma'           => array( '122,4', false ),
			'multiple decimals'    => array( '122.41.94', false ),
		);
	}

	public function get_subdivision_values(): array {
		return array(
			'US state CA'           => array( 'CA', true ),
			'lowercase CA'          => array( 'ca', true, 'CA' ),
			'US state NY'           => array( 'NY', true ),
			'Canada province ON'    => array( 'ON', true ),
			'UK region ENG'         => array( 'ENG', true ),
			'ENG with spaces'       => array( ' ENG ', true, 'ENG' ),
			'Germany Bavaria BY'    => array( 'BY', true ),
			'Australia NSW'         => array( 'NSW', true ),
			'with hyphen'           => array( 'AB-CD', true ),
			'with numbers'          => array( 'CA1', true ),
			'with multiple hyphens' => array( 'A-B-C', true ),
			'alphanumeric mix'      => array( 'A1B2C3', true ),
			'max length 10 chars'   => array( 'ABCDEFGHIJ', true ),
			'exceeds max length'    => array( 'ABCDEFGHIJK', false ),
			'with spaces'           => array( 'CA NY', false ),
			'with special chars'    => array( 'CA_NY', false ),
			'with dots'             => array( 'CA.NY', false ),
			'with slash'            => array( 'CA/NY', false ),
		);
	}

	/**
	 * @dataProvider invalid_type_provider
	 */
	public function test_fields_reject_invalid_types( string $field_name, $invalid_value, string $getter_method, $expected_default ): void {
		$data        = array( $field_name => $invalid_value );
		$coordinates = GeoCoordinates::from_array( $data );

		$this->assertSame( $expected_default, $coordinates->$getter_method() );
	}

	public function invalid_type_provider(): array {
		return array(
			'latitude with array'      => array( 'latitude', array( '37.7749' ), 'latitude', null ),
			'longitude with array'     => array( 'longitude', array( '-122' ), 'longitude', null ),
			'subdivision with array'   => array(
				'subdivision',
				array( 'CA' ),
				'subdivision',
				null,
			),
			'subdivision with integer' => array( 'subdivision', 95, 'subdivision', null ),
			'country_code with array'  => array(
				'country_code',
				array( 'US' ),
				'country_code',
				null,
			),
			'country_code with int'    => array( 'country_code', 1, 'country_code', null ),
		);
	}

	/**
	 * Tests that multiple validation errors are all returned together.
	 */
	public function test_multiple_validation_errors_returned_together(): void {
		$data = array(
			'latitude'     => '100',
			'longitude'    => '200',
			'subdivision'  => 'invalid-subdiv',
			'country_code' => 'USA',
		);

		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();

		$this->assertCount( 4, $issues, 'Should return all validation errors at once' );

		$fields = array_map(
			function ( $issue ) {
				return $issue->to_array()['field'];
			},
			$issues
		);

		$this->assertContains( 'latitude', $fields );
		$this->assertContains( 'longitude', $fields );
		$this->assertContains( 'subdivision', $fields );
		$this->assertContains( 'country_code', $fields );
	}
}
