<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Schema\Money
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
		$this->assertFieldFormat( 'currency_code', $this->get_currency_code_format_cases() );
		$this->assertFieldFormat( 'value', $this->get_money_value_cases() );
	}

	// =========================================================================
	// create()
	// =========================================================================

	/**
	 * GIVEN a numeric value and a currency code
	 * WHEN create() is called with a float, int, or string value
	 * THEN a Money instance is returned and to_array() produces the correct structure
	 *
	 * @dataProvider create_valid_inputs_provider
	 *
	 * @param int|float|string $value          The input value.
	 * @param string           $currency_code  The currency code.
	 * @param string           $expected_value The expected formatted decimal string.
	 */
	public function test_create_returns_money_with_correct_structure( $value, string $currency_code, string $expected_value ): void {
		$money = Money::create( $value, $currency_code );
		$array = $money->to_array();

		$this->assertSame( $currency_code, $array['currency_code'] );
		$this->assertSame( $expected_value, $array['value'] );
	}

	public function create_valid_inputs_provider(): array {
		return array(
			'float value'          => array( 10.5, 'USD', '10.50' ),
			'integer value'        => array( 25, 'EUR', '25.00' ),
			'string decimal value' => array( '9.99', 'GBP', '9.99' ),
			'zero float'           => array( 0.0, 'USD', '0.00' ),
			'large value'          => array( 999.99, 'JPY', '999.99' ),
		);
	}

	/**
	 * GIVEN the same value and currency fed via create() and via from_array()
	 * WHEN to_array() is called on each resulting instance
	 * THEN both produce identical output, proving create() delegates to from_array()
	 */
	public function test_create_and_from_array_produce_identical_to_array_output(): void {
		$value    = 42.5;
		$currency = 'USD';

		$via_create     = Money::create( $value, $currency );
		$via_from_array = Money::from_array(
			array( 'currency_code' => $currency, 'value' => $value ),
			new StoreValidation()
		);

		$this->assertSame( $via_from_array->to_array(), $via_create->to_array() );
	}

	// =========================================================================
	// to_decimal()
	// =========================================================================

	/**
	 * GIVEN a numeric value of any supported type
	 * WHEN to_decimal() is called
	 * THEN a string with exactly two decimal places is returned
	 *
	 * @dataProvider to_decimal_provider
	 *
	 * @param int|float|string $input    The input value.
	 * @param string           $expected The expected formatted string.
	 */
	public function test_to_decimal_formats_value_to_two_decimal_string( $input, string $expected ): void {
		$money = Money::create( $input );
		$this->assertSame( $expected, $money->to_decimal() );
	}

	public function to_decimal_provider(): array {
		return array(
			'integer zero'                => array( 0, '0.00' ),
			'float zero'                  => array( 0.0, '0.00' ),
			'string zero'                 => array( '0', '0.00' ),
			'positive integer'            => array( 10, '10.00' ),
			'positive float two decimals' => array( 10.50, '10.50' ),
			'positive float one decimal'  => array( 9.9, '9.90' ),
			'positive float no decimal'   => array( 25.0, '25.00' ),
			'string decimal value'        => array( '12.50', '12.50' ),
			'string integer'              => array( '7', '7.00' ),
			'large value'                 => array( 999999.99, '999999.99' ),
			'rounds half-up at third dp'  => array( 7.999, '8.00' ),
			'rounds up below half'        => array( 1.005, '1.01' ),
			'rounds down below half'      => array( 1.004, '1.00' ),
			'negative integer'            => array( - 5, '-5.00' ),
			'negative float'              => array( - 10.5, '-10.50' ),
			'negative string'             => array( '-3.14', '-3.14' ),
		);
	}

	// =========================================================================
	// to_array()
	// =========================================================================

	/**
	 * GIVEN a Money instance created via create()
	 * WHEN to_array() is called
	 * THEN the result has exactly the keys currency_code and value,
	 *      value is a decimal-formatted string (not a float),
	 *      and currency_code is the uppercased code
	 */
	public function test_to_array_returns_correct_keys_and_string_value_after_create(): void {
		$money = Money::create( 10.0, 'USD' );
		$array = $money->to_array();

		$this->assertArrayHasKey( 'currency_code', $array );
		$this->assertArrayHasKey( 'value', $array );
		$this->assertSame( 'USD', $array['currency_code'] );
		$this->assertSame( '10.00', $array['value'] );
		$this->assertIsString( $array['value'], 'value must be a string, not a float' );
	}

	/**
	 * GIVEN a Money instance created via from_array() with lowercase currency
	 * WHEN to_array() is called
	 * THEN the value is a decimal-formatted string and currency_code is uppercased
	 */
	public function test_to_array_returns_correct_keys_and_string_value_after_from_array(): void {
		$money = Money::from_array(
			array( 'currency_code' => 'eur', 'value' => '19.99' ),
			new StoreValidation()
		);
		$array = $money->to_array();

		$this->assertSame( 'EUR', $array['currency_code'] );
		$this->assertSame( '19.99', $array['value'] );
		$this->assertIsString( $array['value'], 'value must be a string, not a float' );
	}

	// =========================================================================
	// to_price()
	// =========================================================================

	/**
	 * GIVEN a Money instance with a known value and currency
	 * WHEN to_price() is called
	 * THEN a string in the format "{value} {currency_code}" is returned,
	 *      locale-independent and always using a period as decimal separator
	 *
	 * @dataProvider to_price_provider
	 *
	 * @param int|float|string $value          Input value.
	 * @param string           $currency_code  Currency code.
	 * @param string           $expected_price Expected price string.
	 */
	public function test_to_price_returns_formatted_price_string( $value, string $currency_code, string $expected_price ): void {
		$money = Money::create( $value, $currency_code );

		$this->assertSame( $expected_price, $money->to_price() );
	}

	public function to_price_provider(): array {
		return array(
			'integer value USD' => array( 10, 'USD', '10.00 USD' ),
			'float value EUR'   => array( 9.99, 'EUR', '9.99 EUR' ),
			'zero value GBP'    => array( 0.0, 'GBP', '0.00 GBP' ),
			'large value JPY'   => array( 1000.0, 'JPY', '1000.00 JPY' ),
			'string value CHF'  => array( '42.50', 'CHF', '42.50 CHF' ),
		);
	}
}
