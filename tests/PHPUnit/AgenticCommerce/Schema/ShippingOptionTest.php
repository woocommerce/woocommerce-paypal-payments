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
				'currency_code' => 'usd',
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

	protected function get_data_types(): array {
		return array(
			'id'                 => array( 'type' => 'string', 'default' => '' ),
			'name'               => array( 'type' => 'string', 'default' => '' ),
			'description'        => 'string',
			'isSelected'         => array(
				'type'    => 'bool',
				'getter'  => 'is_selected',
				'default' => false,
			),
			'estimated_delivery' => 'date',
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

		$this->assertBooleanFieldDefaultState( 'isSelected', false );

		$this->assertOptionalField( 'description' );
		$this->assertOptionalField( 'estimated_delivery' );
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'id', 'STANDARD' );
		$this->assertWhitespaceTrimming( 'name', 'Standard' );
		$this->assertWhitespaceTrimming( 'description', 'Description' );
		$this->assertWhitespaceTrimming( 'estimated_delivery', '2024-07-01' );

		$this->assertEmptyStringPreserved( 'description' );

		$this->assertFieldIsCaseSensitive( 'id', 'STANDARD' );
		$this->assertFieldIsCaseSensitive( 'name', 'Standard' );
		$this->assertFieldIsCaseSensitive( 'description', 'Description' );

		$this->assertFieldAcceptsSpecialCharacters( 'id' );
		$this->assertFieldAcceptsSpecialCharacters( 'name' );
		$this->assertFieldAcceptsSpecialCharacters( 'description' );
	}

	public function test_field_format_validation(): void {
		$this->assertFieldFormat( 'estimated_delivery', $this->getYmdDateFormatCases() );
	}
}
