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

	public function test_field_format_validation(): void {
		$this->assertFieldFormat( 'estimated_delivery', $this->getYmdDateFormatCases() );
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
			'description with array'  => array(
				array_merge( $base_data, array( 'description' => array( 'text' ) ) ),
				'description',
				null,
			),
			'description with int'    => array(
				array_merge( $base_data, array( 'description' => 123 ) ),
				'description',
				null,
			),
			'isSelected with string'  => array(
				array_merge( $base_data, array( 'isSelected' => 'true' ) ),
				'is_selected',
				false,
			),
			'isSelected with integer' => array(
				array_merge( $base_data, array( 'isSelected' => 1 ) ),
				'is_selected',
				false,
			),
		);
	}
}

