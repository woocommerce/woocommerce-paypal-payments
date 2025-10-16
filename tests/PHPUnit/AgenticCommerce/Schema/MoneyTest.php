<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money
 */
class MoneyTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return Money::class;
	}

	protected function get_valid_data(): array {
		return array(
			'currency_code' => 'usd',
			'value'         => '25.00',
		);
	}

	protected function get_expected_data(): array {
		return array(
			'currency_code' => 'USD',
			'value'         => 25.,
		);
	}

	protected function get_data_types(): array {
		return array(
			'currency_code' => array( 'type' => 'currency', 'getter' => 'currency' ),
			'value'         => 'number',
		);
	}

	protected function mandatory_data(): array {
		return array(
			'currency_code' => 'USD',
			'value'         => '25.00',
		);
	}

	public function test_required_fields(): void {
		$this->assertRequiredField( 'currency_code' );
		$this->assertRequiredField( 'value' );

		// Money has no optional fields - all fields are required.
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'currency_code', 'USD' );

		$this->assertStringFieldExactLength( 'currency_code', 3 );
		$this->assertFieldNormalizesToUppercase( 'currency_code', 'usd', 'USD' );
	}

	public function test_field_format_validation(): void {
		$this->assertFieldFormat( 'currency_code', $this->getCurrencyCodeFormatCases() );
		$this->assertFieldFormat( 'value', $this->get_value_cases() );
	}

	public function get_value_cases(): array {
		return array(
			'positive_integer'    => array( '25', true, 25.0 ),
			'positive_decimal'    => array( '25.99', true, 25.99 ),
			'three_decimal_jpy'   => array( '25.500', true, 25.5 ),
			'three_decimal_jpy_2' => array( '25.599', true, 25.599 ),
			'negative_value'      => array( '-10.50', true, - 10.5 ),
			'zero'                => array( '0', true, 0.0 ),
			'large_amount'        => array( '999999.99', true, 999999.99 ),
			'int_amount'          => array( 10, true, 10.0 ),
			'float_amount'        => array( 10.5, true, 10.5 ),
			'non_numeric'         => array( 'abc', false ),
			'too_many_decimals'   => array( '10.1234', false ),
			'invalid_format'      => array( '10,50', false ),
			'empty_string'        => array( '', false ),
		);
	}
}
