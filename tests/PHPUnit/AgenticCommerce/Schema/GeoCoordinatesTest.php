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

	/**
	 * Tests that GeoCoordinates stores and returns the latitude.
	 */
	public function test_latitude_accessor(): void {
		$data        = array( 'latitude' => '37.7749' );
		$coordinates = GeoCoordinates::from_array( $data );

		$this->assertSame( 37.7749, $coordinates->latitude() );
	}

	/**
	 * Tests that GeoCoordinates stores and returns the longitude.
	 */
	public function test_longitude_accessor(): void {
		$data        = array( 'longitude' => '-122.4194' );
		$coordinates = GeoCoordinates::from_array( $data );

		$this->assertSame( - 122.4194, $coordinates->longitude() );
	}

	/**
	 * Tests that GeoCoordinates stores and returns the subdivision.
	 */
	public function test_subdivision_accessor(): void {
		$data        = array( 'subdivision' => 'CA' );
		$coordinates = GeoCoordinates::from_array( $data );

		$this->assertSame( 'CA', $coordinates->subdivision() );
	}

	/**
	 * Tests that GeoCoordinates stores and returns the country_code.
	 */
	public function test_country_code_accessor(): void {
		$data        = array( 'country_code' => 'US' );
		$coordinates = GeoCoordinates::from_array( $data );

		$this->assertSame( 'US', $coordinates->country_code() );
	}

	/**
	 * @dataProvider optional_field_provider
	 */
	public function test_optional_fields_return_null_when_missing( string $field_name, string $getter_method ): void {
		$data        = array();
		$coordinates = GeoCoordinates::from_array( $data );

		$this->assertNull( $coordinates->$getter_method() );
	}

	public function optional_field_provider(): array {
		return array(
			'latitude'     => array( 'latitude', 'latitude' ),
			'longitude'    => array( 'longitude', 'longitude' ),
			'subdivision'  => array( 'subdivision', 'subdivision' ),
			'country_code' => array( 'country_code', 'country_code' ),
		);
	}

	/**
	 * @dataProvider valid_latitude_provider
	 */
	public function test_valid_latitude_values_accepted( $latitude, float $expected ): void {
		$data        = array( 'latitude' => $latitude );
		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( $expected, $coordinates->latitude() );
	}

	public function valid_latitude_provider(): array {
		return array(
			'zero'                 => array( '0', 0. ),
			'positive integer'     => array( '45', 45. ),
			'negative integer'     => array( '-45', - 45. ),
			'positive decimal'     => array( '37.7749', 37.7749 ),
			'negative decimal'     => array( '-33.8688', - 33.8688 ),
			'max positive'         => array( '90', 90. ),
			'max positive decimal' => array( '90.0', 90.0 ),
			'max negative'         => array( '-90', - 90. ),
			'max negative decimal' => array( '-90.0', - 90.0 ),
			'small positive'       => array( '0.0001', 0.0001 ),
			'small negative'       => array( '-0.0001', - 0.0001 ),
			'single digit'         => array( '5', 5. ),
			'89.9999'              => array( '89.9999', 89.9999 ),
			'int'                  => array( 23, 23. ),
			'float'                => array( 23.0, 23. ),
		);
	}

	/**
	 * @dataProvider invalid_latitude_provider
	 */
	public function test_invalid_latitude_values_rejected( string $latitude ): void {
		$data        = array( 'latitude' => $latitude );
		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();
		$issue_data  = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'latitude', $issue_data['field'] );
	}

	public function invalid_latitude_provider(): array {
		return array(
			'exceeds max'       => array( '90.1' ),
			'exceeds min'       => array( '-90.1' ),
			'way too large'     => array( '180' ),
			'way too small'     => array( '-180' ),
			'non-numeric'       => array( 'abc' ),
			'with units'        => array( '45°' ),
			'with comma'        => array( '45,5' ),
			'multiple decimals' => array( '45.5.5' ),
		);
	}

	/**
	 * @dataProvider valid_longitude_provider
	 */
	public function test_valid_longitude_values_accepted( $longitude, float $expected ): void {
		$data        = array( 'longitude' => $longitude );
		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( $expected, $coordinates->longitude() );
	}

	public function valid_longitude_provider(): array {
		return array(
			'zero'                 => array( '0', 0. ),
			'positive integer'     => array( '90', 90. ),
			'negative integer'     => array( '-90', - 90. ),
			'positive decimal'     => array( '122.4194', 122.4194 ),
			'negative decimal'     => array( '-122.4194', - 122.4194 ),
			'max positive'         => array( '180', 180. ),
			'max positive decimal' => array( '180.0', 180.0 ),
			'max negative'         => array( '-180', - 180. ),
			'max negative decimal' => array( '-180.0', - 180.0 ),
			'small positive'       => array( '0.0001', 0.0001 ),
			'small negative'       => array( '-0.0001', - 0.0001 ),
			'single digit'         => array( '5', 5. ),
			'179.9999'             => array( '179.9999', 179.9999 ),
			'int'                  => array( 23, 23. ),
			'float'                => array( 23.0, 23. ),
		);
	}

	/**
	 * @dataProvider invalid_longitude_provider
	 */
	public function test_invalid_longitude_values_rejected( string $longitude ): void {
		$data        = array( 'longitude' => $longitude );
		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();
		$issue_data  = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'longitude', $issue_data['field'] );
	}

	public function invalid_longitude_provider(): array {
		return array(
			'exceeds max'       => array( '180.1' ),
			'exceeds min'       => array( '-180.1' ),
			'way too large'     => array( '360' ),
			'way too small'     => array( '-360' ),
			'non-numeric'       => array( 'xyz' ),
			'with units'        => array( '-122°' ),
			'with comma'        => array( '122,4' ),
			'multiple decimals' => array( '122.41.94' ),
		);
	}

	/**
	 * @dataProvider valid_subdivision_provider
	 */
	public function test_valid_subdivision_values_accepted( string $subdivision ): void {
		$data        = array( 'subdivision' => $subdivision );
		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( $subdivision, $coordinates->subdivision() );
	}

	public function valid_subdivision_provider(): array {
		return array(
			'US state CA'           => array( 'CA' ),
			'US state NY'           => array( 'NY' ),
			'Canada province ON'    => array( 'ON' ),
			'UK region ENG'         => array( 'ENG' ),
			'Germany Bavaria BY'    => array( 'BY' ),
			'Australia NSW'         => array( 'NSW' ),
			'with hyphen'           => array( 'AB-CD' ),
			'with numbers'          => array( 'CA1' ),
			'with multiple hyphens' => array( 'A-B-C' ),
			'max length 10 chars'   => array( 'ABCDEFGHIJ' ),
			'alphanumeric mix'      => array( 'A1B2C3' ),
		);
	}

	/**
	 * @dataProvider invalid_subdivision_provider
	 */
	public function test_invalid_subdivision_values_rejected( string $subdivision, string $reason ): void {
		$data        = array( 'subdivision' => $subdivision );
		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();
		$issue_data  = $issues[0]->to_array();

		$this->assertCount( 1, $issues, $reason );
		$this->assertSame( 'subdivision', $issue_data['field'] );
	}

	public function invalid_subdivision_provider(): array {
		return array(
			'exceeds max length' => array( 'ABCDEFGHIJK', 'Exceeds 10 character limit' ),
			'with spaces'        => array( 'CA NY', 'Spaces not allowed' ),
			'with special chars' => array( 'CA_NY', 'Underscores not allowed' ),
			'with dots'          => array( 'CA.NY', 'Dots not allowed' ),
			'with slash'         => array( 'CA/NY', 'Slashes not allowed' ),
		);
	}

	/**
	 * Tests that subdivision with exactly 10 characters is valid.
	 */
	public function test_subdivision_at_max_length_is_valid(): void {
		$data = array(
			'subdivision' => 'ABCDEFGHIJ', // Exactly 10 chars
		);

		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * @dataProvider valid_country_code_provider
	 */
	public function test_valid_country_codes_accepted( string $country_code ): void {
		$data        = array( 'country_code' => $country_code );
		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( $country_code, $coordinates->country_code() );
	}

	public function valid_country_code_provider(): array {
		return array(
			'United States'  => array( 'US' ),
			'Canada'         => array( 'CA' ),
			'United Kingdom' => array( 'GB' ),
			'Germany'        => array( 'DE' ),
			'France'         => array( 'FR' ),
			'Australia'      => array( 'AU' ),
			'Japan'          => array( 'JP' ),
			'China'          => array( 'CN' ),
		);
	}

	/**
	 * @dataProvider invalid_country_code_provider
	 */
	public function test_invalid_country_codes_rejected( string $country_code, string $reason ): void {
		$data        = array( 'country_code' => $country_code );
		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();
		$issue_data  = $issues[0]->to_array();

		$this->assertCount( 1, $issues, $reason );
		$this->assertSame( 'country_code', $issue_data['field'] );
	}

	public function invalid_country_code_provider(): array {
		return array(
			'lowercase'    => array( 'us', 'Lowercase not allowed' ),
			'mixed case'   => array( 'Us', 'Mixed case not allowed' ),
			'single char'  => array( 'U', 'Must be exactly 2 characters' ),
			'three chars'  => array( 'USA', 'Must be exactly 2 characters' ),
			'with numbers' => array( 'U1', 'Numbers not allowed' ),
			'with special' => array( 'U-', 'Special characters not allowed' ),
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

	/**
	 * Tests that leading/trailing whitespace is trimmed from string values.
	 *
	 * @dataProvider whitespace_trimming_provider
	 */
	public function test_string_values_are_trimmed( string $field_name, string $input_value, $expected_value ): void {
		$data        = array( $field_name => $input_value );
		$coordinates = GeoCoordinates::from_array( $data );

		$this->assertSame( $expected_value, $coordinates->$field_name() );
	}

	public function whitespace_trimming_provider(): array {
		return array(
			'latitude with leading space'   => array( 'latitude', ' 37.7749', 37.7749 ),
			'latitude with trailing space'  => array( 'latitude', '37.7749 ', 37.7749 ),
			'latitude with both'            => array( 'latitude', '  37.7749  ', 37.7749 ),
			'longitude with leading space'  => array(
				'longitude',
				' -122.4194',
				- 122.4194,
			),
			'longitude with trailing space' => array(
				'longitude',
				'-122.4194 ',
				- 122.4194,
			),
			'subdivision with spaces'       => array( 'subdivision', ' CA ', 'CA' ),
			'country_code with spaces'      => array( 'country_code', ' US ', 'US' ),
		);
	}

	/**
	 * Tests that coordinates at exact boundaries are valid.
	 */
	public function test_boundary_coordinates_are_valid(): void {
		$data = array(
			'latitude'  => '90.0',
			'longitude' => '180.0',
		);

		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that negative boundary coordinates are valid.
	 */
	public function test_negative_boundary_coordinates_are_valid(): void {
		$data = array(
			'latitude'  => '-90.0',
			'longitude' => '-180.0',
		);

		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests complete valid coordinate set from schema examples.
	 */
	public function test_complete_us_location(): void {
		$data = array(
			'latitude'     => '40.7128',
			'longitude'    => '-74.0060',
			'subdivision'  => 'NY',
			'country_code' => 'US',
		);

		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 40.7128, $coordinates->latitude() );
		$this->assertSame( - 74.0060, $coordinates->longitude() );
		$this->assertSame( 'NY', $coordinates->subdivision() );
		$this->assertSame( 'US', $coordinates->country_code() );
	}

	/**
	 * Tests complete valid coordinate set for international location.
	 */
	public function test_complete_international_location(): void {
		$data = array(
			'latitude'     => '51.5074',
			'longitude'    => '-0.1278',
			'subdivision'  => 'ENG',
			'country_code' => 'GB',
		);

		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();

		$this->assertEmpty( $issues );
		$this->assertSame( 51.5074, $coordinates->latitude() );
		$this->assertSame( - 0.1278, $coordinates->longitude() );
		$this->assertSame( 'ENG', $coordinates->subdivision() );
		$this->assertSame( 'GB', $coordinates->country_code() );
	}

	/**
	 * Tests that zero coordinates are valid (Gulf of Guinea).
	 */
	public function test_zero_coordinates_are_valid(): void {
		$data = array(
			'latitude'  => '0',
			'longitude' => '0',
		);

		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that integer string coordinates without decimals are valid.
	 */
	public function test_integer_coordinates_without_decimals(): void {
		$data = array(
			'latitude'  => '45',
			'longitude' => '-90',
		);

		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that very precise coordinates with many decimals are valid.
	 */
	public function test_high_precision_coordinates(): void {
		$data = array(
			'latitude'  => '37.774929',
			'longitude' => '-122.419416',
		);

		$coordinates = GeoCoordinates::from_array( $data );
		$issues      = $coordinates->validate();

		$this->assertEmpty( $issues );
	}
}
