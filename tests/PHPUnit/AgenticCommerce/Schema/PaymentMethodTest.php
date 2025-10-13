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
			'type' => 'paypal',
		);
	}

	/**
	 * Tests that PaymentMethod stores and returns field values correctly.
	 *
	 * @dataProvider field_accessor_provider
	 */
	public function test_field_accessors( array $data, string $accessor, $expected ): void {
		$method = PaymentMethod::from_array( $data );

		$this->assertSame( $expected, $method->$accessor() );
	}

	public function field_accessor_provider(): array {
		return array(
			'type field'     => array(
				'data'     => array( 'type' => 'paypal' ),
				'accessor' => 'type',
				'expected' => 'paypal',
			),
			'token field'    => array(
				'data'     => array(
					'type'  => 'paypal',
					'token' => 'EC-7U8939823K567',
				),
				'accessor' => 'token',
				'expected' => 'EC-7U8939823K567',
			),
			'payer_id field' => array(
				'data'     => array(
					'type'     => 'paypal',
					'payer_id' => 'PAYER123456789',
				),
				'accessor' => 'payer_id',
				'expected' => 'PAYER123456789',
			),
		);
	}

	/**
	 * Tests that optional fields return null when not provided.
	 */
	public function test_optional_fields_return_null_when_missing(): void {
		$data   = array( 'type' => 'paypal' );
		$method = PaymentMethod::from_array( $data );

		$this->assertNull( $method->token() );
		$this->assertNull( $method->payer_id() );
	}

	/**
	 * Tests validation scenarios.
	 *
	 * @dataProvider validation_provider
	 */
	public function test_validation( array $data, int $expected_issue_count, ?array $expected_issue = null ): void {
		$method = PaymentMethod::from_array( $data );
		$issues = $method->validate();

		$this->assertCount( $expected_issue_count, $issues );

		if ( $expected_issue ) {
			$issue_data = $issues[0]->to_array();
			$this->assertSame( $expected_issue['code'], $issue_data['code'] );
			$this->assertSame( $expected_issue['type'], $issue_data['type'] );
			if ( isset( $expected_issue['field'] ) ) {
				$this->assertSame( $expected_issue['field'], $issue_data['field'] );
			}
		}
	}

	public function validation_provider(): array {
		return array(
			'valid payment method' => array(
				'data'                 => array(
					'type'     => 'paypal',
					'token'    => 'EC-7U8939823K567',
					'payer_id' => 'PAYER123456789',
				),
				'expected_issue_count' => 0,
				'expected_issue'       => null,
			),
			'invalid payment type' => array(
				'data'                 => array( 'type' => 'credit_card' ),
				'expected_issue_count' => 1,
				'expected_issue'       => array(
					'code' => 'DATA_ERROR',
					'type' => 'INVALID_DATA',
				),
			),
		);
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
	 * Tests that empty strings are treated as missing values.
	 *
	 * @dataProvider empty_string_provider
	 */
	public function test_empty_strings_treated_as_null( array $data, string $getter_method ): void {
		$method = PaymentMethod::from_array( $data );

		$this->assertNull( $method->$getter_method() );
	}

	public function empty_string_provider(): array {
		return array(
			'token empty string'    => array(
				'data'          => array(
					'type'  => 'paypal',
					'token' => '',
				),
				'getter_method' => 'token',
			),
			'payer_id empty string' => array(
				'data'          => array(
					'type'     => 'paypal',
					'payer_id' => '',
				),
				'getter_method' => 'payer_id',
			),
		);
	}

	/**
	 * Tests that string values are trimmed.
	 *
	 * @dataProvider whitespace_trimming_provider
	 */
	public function test_string_values_are_trimmed( array $data, string $getter_method, string $expected ): void {
		$method = PaymentMethod::from_array( $data );

		$this->assertSame( $expected, $method->$getter_method() );
	}

	public function whitespace_trimming_provider(): array {
		return array(
			'token with spaces'    => array(
				'data'          => array(
					'type'  => 'paypal',
					'token' => '  EC-7U8939823K567  ',
				),
				'getter_method' => 'token',
				'expected'      => 'EC-7U8939823K567',
			),
			'payer_id with spaces' => array(
				'data'          => array(
					'type'     => 'paypal',
					'payer_id' => '  PAYER123456789  ',
				),
				'getter_method' => 'payer_id',
				'expected'      => 'PAYER123456789',
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
