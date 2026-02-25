<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

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
}
