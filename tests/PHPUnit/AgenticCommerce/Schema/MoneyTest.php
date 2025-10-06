<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidData;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\MissingField;

/**
 * @covers Money
 */
class MoneyTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return Money::class;
	}

	protected function get_valid_data(): array {
		return array(
			'currency_code' => 'USD',
			'value'         => '25.00',
		);
	}

	/**
	 * Tests that Money correctly parses valid currency codes.
	 *
	 * @dataProvider valid_currency_provider
	 */
	public function test_valid_currency_codes( string $input, string $expected ): void {
		$data  = array(
			'currency_code' => $input,
			'value'         => '10.00',
		);
		$money = Money::from_array( $data );

		$this->assertEmpty( $money->validate() );
		$this->assertSame( $expected, $money->currency() );
	}

	public function valid_currency_provider(): array {
		return array(
			'usd'           => array(
				'input'    => 'USD',
				'expected' => 'USD',
			),
			'eur'           => array(
				'input'    => 'EUR',
				'expected' => 'EUR',
			),
			'gbp'           => array(
				'input'    => 'GBP',
				'expected' => 'GBP',
			),
			'jpy'           => array(
				'input'    => 'JPY',
				'expected' => 'JPY',
			),
			'lowercase_usd' => array(
				'input'    => 'usd',
				'expected' => 'USD',
			),
			'mixed_case'    => array(
				'input'    => 'EuR',
				'expected' => 'EUR',
			),
		);
	}

	/**
	 * Tests that Money correctly parses various valid value formats.
	 *
	 * @dataProvider valid_value_provider
	 */
	public function test_valid_values( $value, float $expected ): void {
		$data  = array(
			'currency_code' => 'USD',
			'value'         => $value,
		);
		$money = Money::from_array( $data );

		$this->assertEmpty( $money->validate() );
		$this->assertSame( $expected, $money->value() );
	}

	public function valid_value_provider(): array {
		return array(
			'positive_integer'    => array(
				'value'    => '25',
				'expected' => 25.0,
			),
			'positive_decimal'    => array(
				'value'    => '25.99',
				'expected' => 25.99,
			),
			'three_decimal_jpy'   => array(
				'value'    => '25.500',
				'expected' => 25.5,
			),
			'three_decimal_jpy_2' => array(
				'value'    => '25.599',
				'expected' => 25.599,
			),
			'negative_value'      => array(
				'value'    => '-10.50',
				'expected' => - 10.5,
			),
			'zero'                => array(
				'value'    => '0',
				'expected' => 0.0,
			),
			'large_amount'        => array(
				'value'    => '999999.99',
				'expected' => 999999.99,
			),
			'int_amount'          => array(
				'value'    => 10,
				'expected' => 10.0,
			),
			'float_amount'        => array(
				'value'    => 10.5,
				'expected' => 10.5,
			),
		);
	}

	/**
	 * Tests that invalid currency codes produce validation issues.
	 *
	 * @dataProvider invalid_currency_provider
	 */
	public function test_invalid_currency_codes( array $data, string $expected_message_fragment ): void {
		$money  = Money::from_array( $data );
		$issues = $money->validate();

		$this->assertCount( 1, $issues );

		$issue_data = $issues[0]->to_array();
		$this->assertSame( 'currency_code', $issue_data['field'] );
		$this->assertInstanceOf( InvalidData::class, $issues[0] );
		$this->assertStringContainsString( $expected_message_fragment, $issue_data['user_message'] );
		$this->assertSame( '', $money->currency() );
	}

	public function invalid_currency_provider(): array {
		return array(
			'too_short'  => array(
				'data'                      => array(
					'currency_code' => 'US',
					'value'         => '10.00',
				),
				'expected_message_fragment' => 'valid 3-letter currency code',
			),
			'too_long'   => array(
				'data'                      => array(
					'currency_code' => 'USDD',
					'value'         => '10.00',
				),
				'expected_message_fragment' => 'valid 3-letter currency code',
			),
			'empty'      => array(
				'data'                      => array(
					'currency_code' => '',
					'value'         => '10.00',
				),
				'expected_message_fragment' => 'valid 3-letter currency code',
			),
			'whitespace' => array(
				'data'                      => array(
					'currency_code' => '   ',
					'value'         => '10.00',
				),
				'expected_message_fragment' => 'valid 3-letter currency code',
			),
		);
	}

	/**
	 * Tests that invalid values produce validation issues.
	 *
	 * @dataProvider invalid_value_provider
	 */
	public function test_invalid_values( array $data, string $expected_message_fragment ): void {
		$money  = Money::from_array( $data );
		$issues = $money->validate();

		$this->assertCount( 1, $issues );

		$issue_data = $issues[0]->to_array();
		$this->assertSame( 'value', $issue_data['field'] );
		$this->assertInstanceOf( InvalidData::class, $issues[0] );
		$this->assertStringContainsString( $expected_message_fragment, $issue_data['user_message'] );
		$this->assertSame( 0.0, $money->value() );
	}

	public function invalid_value_provider(): array {
		return array(
			'non_numeric'       => array(
				'data'                      => array(
					'currency_code' => 'USD',
					'value'         => 'abc',
				),
				'expected_message_fragment' => 'valid numerical value',
			),
			'too_many_decimals' => array(
				'data'                      => array(
					'currency_code' => 'USD',
					'value'         => '10.1234',
				),
				'expected_message_fragment' => 'valid numerical value',
			),
			'invalid_format'    => array(
				'data'                      => array(
					'currency_code' => 'USD',
					'value'         => '10,50',
				),
				'expected_message_fragment' => 'valid numerical value',
			),
			'empty_string'      => array(
				'data'                      => array(
					'currency_code' => 'USD',
					'value'         => '',
				),
				'expected_message_fragment' => 'valid numerical value',
			),
		);
	}

	/**
	 * Tests that missing currency_code produces validation issue.
	 */
	public function test_missing_currency_code(): void {
		$data   = array( 'value' => '10.00' );
		$money  = Money::from_array( $data );
		$issues = $money->validate();

		$this->assertCount( 1, $issues );

		$issue_data = $issues[0]->to_array();
		$this->assertSame( 'currency_code', $issue_data['field'] );
		$this->assertInstanceOf( MissingField::class, $issues[0] );
		$this->assertStringContainsString( 'currency code', $issue_data['user_message'] );
	}

	/**
	 * Tests that missing value produces validation issue.
	 */
	public function test_missing_value(): void {
		$data   = array( 'currency_code' => 'USD' );
		$money  = Money::from_array( $data );
		$issues = $money->validate();

		$this->assertCount( 1, $issues );

		$issue_data = $issues[0]->to_array();
		$this->assertSame( 'value', $issue_data['field'] );
		$this->assertInstanceOf( MissingField::class, $issues[0] );
		$this->assertStringContainsString( 'value', $issue_data['user_message'] );
	}

	/**
	 * Tests that multiple validation issues are collected.
	 */
	public function test_multiple_validation_issues(): void {
		$data   = array(); // Both fields missing.
		$money  = Money::from_array( $data );
		$issues = $money->validate();

		$this->assertCount( 2, $issues, 'Should collect all validation issues' );
	}
}
