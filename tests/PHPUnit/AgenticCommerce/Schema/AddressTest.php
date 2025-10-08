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
			'country_code' => 'US',
		);
	}

	/**
	 * Tests that Address correctly validates and normalizes country codes.
	 *
	 * @dataProvider valid_country_code_provider
	 */
	public function test_valid_country_codes( string $input, string $expected ): void {
		$data    = array( 'country_code' => $input );
		$address = Address::from_array( $data );

		$this->assertEmpty( $address->validate() );
		$this->assertSame( $expected, $address->country_code() );
	}

	public function valid_country_code_provider(): array {
		return array(
			'uppercase_us'     => array(
				'input'    => 'US',
				'expected' => 'US',
			),
			'lowercase_us'     => array(
				'input'    => 'us',
				'expected' => 'US',
			),
			'lowercase_de'     => array(
				'input'    => 'de',
				'expected' => 'DE',
			),
			'with_whitespace'  => array(
				'input'    => '  GB  ',
				'expected' => 'GB',
			),
		);
	}

	/**
	 * Tests that country codes with invalid length produce validation issues.
	 */
	public function test_country_code_too_long_produces_validation_issue(): void {
		$data    = array( 'country_code' => 'USA' );
		$address = Address::from_array( $data );
		$issues  = $address->validate();

		$this->assertCount( 1, $issues );
		$this->assertSame( '', $address->country_code() );
	}

	/**
	 * Tests that country codes that are too short produce validation issues.
	 */
	public function test_country_code_too_short_produces_validation_issue(): void {
		$data    = array( 'country_code' => 'U' );
		$address = Address::from_array( $data );
		$issues  = $address->validate();

		$this->assertCount( 1, $issues );
		$this->assertSame( '', $address->country_code() );
	}

	/**
	 * Tests that missing country_code produces validation issue.
	 */
	public function test_missing_country_code(): void {
		$data    = array();
		$address = Address::from_array( $data );
		$issues  = $address->validate();

		$this->assertCount( 1, $issues );

		$issue_data = $issues[0]->to_array();
		$this->assertSame( 'country_code', $issue_data['field'] );
	}

	/**
	 * Tests that optional fields are stored correctly.
	 *
	 * @dataProvider optional_field_storage_provider
	 */
	public function test_optional_fields_are_stored( string $field_name, string $value ): void {
		$data              = array( 'country_code' => 'US' );
		$data[ $field_name ] = $value;
		$address           = Address::from_array( $data );

		$this->assertEmpty( $address->validate() );
		$this->assertSame( $value, $address->$field_name() );
	}

	public function optional_field_storage_provider(): array {
		return array(
			'address_line_1' => array(
				'field_name' => 'address_line_1',
				'value'      => '123 Main Street',
			),
			'address_line_2' => array(
				'field_name' => 'address_line_2',
				'value'      => 'Apt 4B',
			),
			'admin_area_2'   => array(
				'field_name' => 'admin_area_2',
				'value'      => 'San Jose',
			),
			'admin_area_1'   => array(
				'field_name' => 'admin_area_1',
				'value'      => 'CA',
			),
			'postal_code'    => array(
				'field_name' => 'postal_code',
				'value'      => '95131',
			),
		);
	}

	/**
	 * Tests that optional fields return null when not provided.
	 *
	 * @dataProvider optional_field_names_provider
	 */
	public function test_optional_fields_return_null_when_not_provided( string $field_name ): void {
		$data    = array( 'country_code' => 'US' );
		$address = Address::from_array( $data );

		$this->assertEmpty( $address->validate() );
		$this->assertNull( $address->$field_name() );
	}

	public function optional_field_names_provider(): array {
		return array(
			'address_line_1' => array( 'field_name' => 'address_line_1' ),
			'address_line_2' => array( 'field_name' => 'address_line_2' ),
			'admin_area_2'   => array( 'field_name' => 'admin_area_2' ),
			'admin_area_1'   => array( 'field_name' => 'admin_area_1' ),
			'postal_code'    => array( 'field_name' => 'postal_code' ),
		);
	}

	/**
	 * Tests that optional fields exceeding max length produce validation issues.
	 *
	 * @dataProvider field_max_length_provider
	 */
	public function test_fields_exceeding_max_length_produce_validation_issue( string $field_name, int $max_length ): void {
		$data              = array( 'country_code' => 'US' );
		$data[ $field_name ] = str_repeat( 'X', $max_length + 1 );
		$address           = Address::from_array( $data );
		$issues            = $address->validate();

		$this->assertCount( 1, $issues );

		$issue_data = $issues[0]->to_array();
		$this->assertSame( $field_name, $issue_data['field'] );
	}

	public function field_max_length_provider(): array {
		return array(
			'address_line_1' => array(
				'field_name' => 'address_line_1',
				'max_length' => 300,
			),
			'address_line_2' => array(
				'field_name' => 'address_line_2',
				'max_length' => 300,
			),
			'admin_area_2'   => array(
				'field_name' => 'admin_area_2',
				'max_length' => 120,
			),
			'admin_area_1'   => array(
				'field_name' => 'admin_area_1',
				'max_length' => 300,
			),
			'postal_code'    => array(
				'field_name' => 'postal_code',
				'max_length' => 60,
			),
		);
	}

	/**
	 * Tests that fields accept their exact max length and store the value.
	 *
	 * @dataProvider field_max_length_provider
	 */
	public function test_fields_accept_exact_max_length( string $field_name, int $max_length ): void {
		$value             = str_repeat( 'X', $max_length );
		$data              = array( 'country_code' => 'US' );
		$data[ $field_name ] = $value;
		$address           = Address::from_array( $data );

		$this->assertEmpty( $address->validate() );
		$this->assertSame( $value, $address->$field_name() );
	}
}
