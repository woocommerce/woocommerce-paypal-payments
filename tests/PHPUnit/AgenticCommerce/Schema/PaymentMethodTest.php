<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers PaymentMethod
 */
class PaymentMethodTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return PaymentMethod::class;
	}

	protected function get_valid_data(): array {
		return array(
			'type'     => 'paypal',
			'token'    => 'EC123456789',
			'payer_id' => 'merchant@example.com',
		);
	}

	protected function get_expected_data(): array {
		return array(
			'type'     => 'paypal',
			'token'    => 'EC123456789',
			'payer_id' => 'merchant@example.com',
		);
	}

	protected function mandatory_data(): array {
		return array(
			'type' => 'paypal',
		);
	}

	public function test_required_fields(): void {
		$this->assertRequiredField( 'type' );
	}

	public function test_optional_fields(): void {
		$this->assertOptionalField( 'token' );
		$this->assertOptionalField( 'payer_id' );
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'type', 'paypal' );
		$this->assertWhitespaceTrimming( 'token', 'EC-123' );
		$this->assertWhitespaceTrimming( 'payer_id', 'PAYER123' );

		$this->assertEmptyStringPreserved( 'token' );
		$this->assertEmptyStringPreserved( 'payer_id' );
	}

	/**
	 * Tests that fields reject invalid types and use defaults.
	 *
	 * @dataProvider invalid_type_provider
	 */
	public function test_fields_reject_invalid_types( array $data, string $getter_method, $expected_default ): void {
		$method = PaymentMethod::from_array( $data );

		$this->assertSame( $expected_default, $method->$getter_method() );
	}

	public function invalid_type_provider(): array {
		return array(
			'token with array'    => array(
				'data'             => array(
					'type'  => 'paypal',
					'token' => array( 'invalid' ),
				),
				'getter_method'    => 'token',
				'expected_default' => null,
			),
			'token with integer'  => array(
				'data'             => array(
					'type'  => 'paypal',
					'token' => 123,
				),
				'getter_method'    => 'token',
				'expected_default' => null,
			),
			'payer_id with array' => array(
				'data'             => array(
					'type'     => 'paypal',
					'payer_id' => array( 'invalid' ),
				),
				'getter_method'    => 'payer_id',
				'expected_default' => null,
			),
			'payer_id with int'   => array(
				'data'             => array(
					'type'     => 'paypal',
					'payer_id' => 456,
				),
				'getter_method'    => 'payer_id',
				'expected_default' => null,
			),
		);
	}

	/**
	 * Tests that invalid/missing type field values produce validation errors.
	 *
	 * @dataProvider missing_type_field_provider
	 */
	public function test_missing_or_invalid_type_field_produces_validation_error( array $data ): void {
		$method = PaymentMethod::from_array( $data );
		$issues = $method->validate();

		$this->assertCount( 1, $issues );

		$issue_data = $issues[0]->to_array();
		$this->assertSame( 'DATA_ERROR', $issue_data['code'] );
		$this->assertSame( 'MISSING_FIELD', $issue_data['type'] );
		$this->assertSame( 'type', $issue_data['field'] );
	}

	public function missing_type_field_provider(): array {
		return array(
			'type empty string'    => array( array( 'type' => '' ) ),
			'type whitespace-only' => array( array( 'type' => '   ' ) ),
			'type with array'      => array( array( 'type' => array( 'paypal' ) ) ),
			'type with integer'    => array( array( 'type' => 123 ) ),
			'type with boolean'    => array( array( 'type' => true ) ),
			'type with null'       => array( array( 'type' => null ) ),
			'type not provided'    => array( array( 'token' => 'EC-123' ) ),
		);
	}
}
