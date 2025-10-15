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

	protected function mandatory_data(): array {
		return array(
			'country_code' => 'US',
		);
	}

	public function test_required_fields(): void {
		$this->assertRequiredField( 'country_code' );
	}

	public function test_optional_fields(): void {
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

		$this->assertStringFieldMaxLength( 'country_code', 2 );
		$this->assertStringFieldMaxLength( 'address_line_1', 300 );
		$this->assertStringFieldMaxLength( 'address_line_2', 300 );
		$this->assertStringFieldMaxLength( 'admin_area_2', 120 );
		$this->assertStringFieldMaxLength( 'admin_area_1', 300 );
		$this->assertStringFieldMaxLength( 'postal_code', 60 );
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
		$data                = array( 'country_code' => 'US' );
		$data[ $field_name ] = $value;
		$address             = Address::from_array( $data );

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
		$data                = array( 'country_code' => 'US' );
		$data[ $field_name ] = str_repeat( 'X', $max_length + 1 );
		$address             = Address::from_array( $data );
		$issues              = $address->validate();

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
		$value               = str_repeat( 'X', $max_length );
		$data                = array( 'country_code' => 'US' );
		$data[ $field_name ] = $value;
		$address             = Address::from_array( $data );

		$this->assertEmpty( $address->validate() );
		$this->assertSame( $value, $address->$field_name() );
	}
}
