<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers ShippingOption
 */
class ShippingOptionTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return ShippingOption::class;
	}

	protected function get_valid_data(): array {
		return array(
			'id'                 => 'STANDARD_SHIPPING',
			'name'               => 'Standard Shipping (5-7 days)',
			'description'        => 'Standard ground shipping via USPS',
			'price'              => array(
				'currency_code' => 'USD',
				'value'         => '5.99',
			),
			'isSelected'         => true,
			'estimated_delivery' => '2024-07-01',
		);
	}

	protected function get_expected_data(): array {
		return array(
			'id'                 => 'STANDARD_SHIPPING',
			'name'               => 'Standard Shipping (5-7 days)',
			'description'        => 'Standard ground shipping via USPS',
			'price.currency'     => 'USD',
			'price.value'        => 5.99,
			'is_selected'        => true,
			'estimated_delivery' => '2024-07-01',
		);
	}

	protected function mandatory_data(): array {
		return array(
			'id'         => 'STANDARD_SHIPPING',
			'name'       => 'Standard Shipping',
			'price'      => array(
				'currency_code' => 'USD',
				'value'         => '5.99',
			),
			'isSelected' => true,
		);
	}

	public function test_required_fields(): void {
		$this->assertRequiredField( 'id' );
		$this->assertRequiredField( 'name' );
		$this->assertRequiredField( 'price' );
		$this->assertRequiredField( 'isSelected' );
	}

	public function test_optional_fields(): void {
		$this->assertOptionalField( 'description' );
		$this->assertOptionalField( 'estimated_delivery' );
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'id', 'STANDARD' );
		$this->assertWhitespaceTrimming( 'name', 'Standard' );

		$this->assertWhitespaceTrimming( 'description', 'Description' );
		$this->assertWhitespaceTrimming( 'estimated_delivery', '2024-07-01' );
		$this->assertEmptyStringPreserved( 'description' );
	}

	// === Required Field Accessors ===

	/**
	 * @dataProvider required_field_accessor_provider
	 */
	public function test_required_field_accessors( array $data, string $accessor, $expected ): void {
		$option = ShippingOption::from_array( $data );

		$this->assertSame( $expected, $option->$accessor() );
	}

	public function required_field_accessor_provider(): array {
		return array(
			'id field'   => array(
				'data'     => array(
					'id'         => 'EXPRESS_SHIPPING',
					'name'       => 'Express',
					'price'      => array( 'currency_code' => 'USD', 'value' => '12.99' ),
					'isSelected' => false,
				),
				'accessor' => 'id',
				'expected' => 'EXPRESS_SHIPPING',
			),
			'name field' => array(
				'data'     => array(
					'id'         => 'STANDARD',
					'name'       => 'Standard Shipping (5-7 days)',
					'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
					'isSelected' => true,
				),
				'accessor' => 'name',
				'expected' => 'Standard Shipping (5-7 days)',
			),
		);
	}

	/**
	 * Tests that isSelected stores and returns boolean values.
	 */
	public function test_is_selected_accessor(): void {
		$data_true = array(
			'id'         => 'STANDARD',
			'name'       => 'Standard',
			'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
			'isSelected' => true,
		);

		$option = ShippingOption::from_array( $data_true );
		$this->assertTrue( $option->is_selected() );

		$data_false = array(
			'id'         => 'EXPRESS',
			'name'       => 'Express',
			'price'      => array( 'currency_code' => 'USD', 'value' => '12.99' ),
			'isSelected' => false,
		);

		$option = ShippingOption::from_array( $data_false );
		$this->assertFalse( $option->is_selected() );
	}

	/**
	 * Tests that price returns Money object.
	 */
	public function test_price_accessor(): void {
		$data   = array(
			'id'         => 'STANDARD',
			'name'       => 'Standard',
			'price'      => array(
				'currency_code' => 'USD',
				'value'         => '5.99',
			),
			'isSelected' => true,
		);
		$option = ShippingOption::from_array( $data );
		$price  = $option->price();

		$this->assertInstanceOf( Money::class, $price );
		$this->assertSame( 'USD', $price->currency() );
		$this->assertSame( 5.99, $price->value() );
	}

	// === Optional Field Accessors ===

	/**
	 * Tests that optional string fields store and return values.
	 *
	 * @dataProvider optional_string_field_provider
	 */
	public function test_optional_string_field_accessors( string $field_name, string $field_value, string $accessor ): void {
		$data = array(
			'id'         => 'STANDARD',
			'name'       => 'Standard',
			'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
			'isSelected' => true,
			$field_name  => $field_value,
		);

		$option = ShippingOption::from_array( $data );

		$this->assertSame( $field_value, $option->$accessor() );
	}

	public function optional_string_field_provider(): array {
		return array(
			'description field'        => array(
				'description',
				'Standard ground shipping via USPS',
				'description',
			),
			'estimated_delivery field' => array(
				'estimated_delivery',
				'2024-07-01',
				'estimated_delivery',
			),
		);
	}

	/**
	 * Tests that optional fields return null when missing.
	 *
	 * @dataProvider optional_field_provider
	 */
	public function test_optional_fields_return_null_when_missing( string $accessor ): void {
		$data   = array(
			'id'         => 'STANDARD',
			'name'       => 'Standard',
			'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
			'isSelected' => true,
		);
		$option = ShippingOption::from_array( $data );

		$this->assertNull( $option->$accessor() );
	}

	public function optional_field_provider(): array {
		return array(
			'description'        => array( 'description' ),
			'estimated_delivery' => array( 'estimated_delivery' ),
		);
	}

	// === Type Safety Tests ===

	/**
	 * Tests that fields reject invalid types.
	 *
	 * @dataProvider invalid_type_provider
	 */
	public function test_fields_reject_invalid_types( array $data, string $accessor, $expected_default ): void {
		$option = ShippingOption::from_array( $data );

		$this->assertSame( $expected_default, $option->$accessor() );
	}

	public function invalid_type_provider(): array {
		$base_data = array(
			'id'         => 'STANDARD',
			'name'       => 'Standard',
			'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
			'isSelected' => true,
		);

		return array(
			'description with array'        => array(
				array_merge( $base_data, array( 'description' => array( 'text' ) ) ),
				'description',
				null,
			),
			'description with int'          => array(
				array_merge( $base_data, array( 'description' => 123 ) ),
				'description',
				null,
			),
			'estimated_delivery with array' => array(
				array_merge( $base_data, array( 'estimated_delivery' => array( '2024-07-01' ) ) ),
				'estimated_delivery',
				null,
			),
			'estimated_delivery with int'   => array(
				array_merge( $base_data, array( 'estimated_delivery' => 20240701 ) ),
				'estimated_delivery',
				null,
			),
			'isSelected with string'        => array(
				array_merge( $base_data, array( 'isSelected' => 'true' ) ),
				'is_selected',
				false,
			),
			'isSelected with integer'       => array(
				array_merge( $base_data, array( 'isSelected' => 1 ) ),
				'is_selected',
				false,
			),
		);
	}

	// === Whitespace Handling Tests ===

	/**
	 * Tests that empty strings are preserved as empty strings.
	 *
	 * @dataProvider empty_string_provider
	 */
	public function test_empty_strings_are_preserved( string $field_name, string $accessor ): void {
		$data = array(
			'id'         => 'STANDARD',
			'name'       => 'Standard',
			'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
			'isSelected' => true,
			$field_name  => '',
		);

		$option = ShippingOption::from_array( $data );

		$this->assertSame( '', $option->$accessor() );
	}

	public function empty_string_provider(): array {
		return array(
			'description' => array( 'description', 'description' ),
		);
	}

	/**
	 * Tests that whitespace-only strings are trimmed to empty strings.
	 *
	 * @dataProvider whitespace_string_provider
	 */
	public function test_whitespace_only_strings_trimmed_to_empty( string $field_name, string $accessor ): void {
		$data = array(
			'id'         => 'STANDARD',
			'name'       => 'Standard',
			'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
			'isSelected' => true,
			$field_name  => '   ',
		);

		$option = ShippingOption::from_array( $data );

		$this->assertSame( '', $option->$accessor() );
	}

	public function whitespace_string_provider(): array {
		return array(
			'description' => array( 'description', 'description' ),
		);
	}

	/**
	 * Tests that string values are trimmed.
	 *
	 * @dataProvider string_trimming_provider
	 */
	public function test_string_values_are_trimmed( string $field_name, string $input_value, string $expected_value, string $accessor ): void {
		$data = array(
			'id'         => 'STANDARD',
			'name'       => 'Standard',
			'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
			'isSelected' => true,
			$field_name  => $input_value,
		);

		$option = ShippingOption::from_array( $data );

		$this->assertSame( $expected_value, $option->$accessor() );
	}

	public function string_trimming_provider(): array {
		return array(
			'id with spaces'                 => array( 'id', '  STANDARD  ', 'STANDARD', 'id' ),
			'name with spaces'               => array(
				'name',
				'  Standard Shipping  ',
				'Standard Shipping',
				'name',
			),
			'description with spaces'        => array(
				'description',
				'  Ground shipping  ',
				'Ground shipping',
				'description',
			),
			'estimated_delivery with spaces' => array(
				'estimated_delivery',
				'  2024-07-01  ',
				'2024-07-01',
				'estimated_delivery',
			),
		);
	}

	// === Validation Tests ===

	/**
	 * Tests validation for missing required fields.
	 *
	 * @dataProvider missing_required_field_provider
	 */
	public function test_missing_required_fields_produce_validation_errors( array $data, string $expected_field ): void {
		$option     = ShippingOption::from_array( $data );
		$issues     = $option->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'DATA_ERROR', $issue_data['code'] );
		$this->assertSame( 'MISSING_FIELD', $issue_data['type'] );
		$this->assertSame( $expected_field, $issue_data['field'] );
	}

	public function missing_required_field_provider(): array {
		return array(
			'missing id'         => array(
				array(
					'name'       => 'Standard',
					'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
					'isSelected' => true,
				),
				'id',
			),
			'missing name'       => array(
				array(
					'id'         => 'STANDARD',
					'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
					'isSelected' => true,
				),
				'name',
			),
			'missing price'      => array(
				array(
					'id'         => 'STANDARD',
					'name'       => 'Standard',
					'isSelected' => true,
				),
				'price',
			),
			'missing isSelected' => array(
				array(
					'id'    => 'STANDARD',
					'name'  => 'Standard',
					'price' => array( 'currency_code' => 'USD', 'value' => '5.99' ),
				),
				'isSelected',
			),
			'id empty string'    => array(
				array(
					'id'         => '',
					'name'       => 'Standard',
					'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
					'isSelected' => true,
				),
				'id',
			),
			'id whitespace'      => array(
				array(
					'id'         => '   ',
					'name'       => 'Standard',
					'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
					'isSelected' => true,
				),
				'id',
			),
			'name empty string'  => array(
				array(
					'id'         => 'STANDARD',
					'name'       => '',
					'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
					'isSelected' => true,
				),
				'name',
			),
			'name whitespace'    => array(
				array(
					'id'         => 'STANDARD',
					'name'       => '   ',
					'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
					'isSelected' => true,
				),
				'name',
			),
			'id non-string'      => array(
				array(
					'id'         => 123,
					'name'       => 'Standard',
					'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
					'isSelected' => true,
				),
				'id',
			),
			'name non-string'    => array(
				array(
					'id'         => 'STANDARD',
					'name'       => array( 'Standard' ),
					'price'      => array( 'currency_code' => 'USD', 'value' => '5.99' ),
					'isSelected' => true,
				),
				'name',
			),
		);
	}

	/**
	 * Tests validation for invalid estimated_delivery format.
	 */
	public function test_invalid_estimated_delivery_format(): void {
		$data       = array(
			'id'                 => 'STANDARD',
			'name'               => 'Standard',
			'price'              => array( 'currency_code' => 'USD', 'value' => '5.99' ),
			'isSelected'         => true,
			'estimated_delivery' => '07/01/2024',
		);
		$option     = ShippingOption::from_array( $data );
		$issues     = $option->validate();
		$issue_data = $issues[0]->to_array();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'DATA_ERROR', $issue_data['code'] );
		$this->assertSame( 'INVALID_DATA', $issue_data['type'] );
		$this->assertSame( 'estimated_delivery', $issue_data['field'] );
	}

	/**
	 * Tests that valid YYYY-MM-DD date format passes validation.
	 */
	public function test_valid_estimated_delivery_format(): void {
		$data   = array(
			'id'                 => 'STANDARD',
			'name'               => 'Standard',
			'price'              => array( 'currency_code' => 'USD', 'value' => '5.99' ),
			'isSelected'         => true,
			'estimated_delivery' => '2024-07-01',
		);
		$option = ShippingOption::from_array( $data );
		$issues = $option->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests validation for invalid price structure.
	 */
	public function test_invalid_price_structure_produces_validation_error(): void {
		$data   = array(
			'id'         => 'STANDARD',
			'name'       => 'Standard',
			'price'      => array( 'invalid' => 'structure' ),
			'isSelected' => true,
		);
		$option = ShippingOption::from_array( $data );
		$issues = $option->validate();

		$this->assertGreaterThan( 0, count( $issues ), 'Invalid Money structure should produce validation issues' );
	}

	/**
	 * Tests that multiple validation errors are returned together.
	 */
	public function test_multiple_validation_errors_returned_together(): void {
		$data   = array(
			'id'                 => '',
			'name'               => '   ',
			'price'              => array( 'currency_code' => 'USD', 'value' => '5.99' ),
			'estimated_delivery' => 'invalid-date',
		);
		$option = ShippingOption::from_array( $data );
		$issues = $option->validate();

		$this->assertGreaterThanOrEqual( 3, count( $issues ), 'Should return multiple validation errors' );

		$fields = array_map(
			function ( $issue ) {
				return $issue->to_array()['field'];
			},
			$issues
		);

		$this->assertContains( 'id', $fields );
		$this->assertContains( 'name', $fields );
		$this->assertContains( 'estimated_delivery', $fields );
	}
}

