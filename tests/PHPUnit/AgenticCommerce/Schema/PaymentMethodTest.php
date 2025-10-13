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
			'valid payment method'   => array(
				'data'                 => array(
					'type'     => 'paypal',
					'token'    => 'EC-7U8939823K567',
					'payer_id' => 'PAYER123456789',
				),
				'expected_issue_count' => 0,
				'expected_issue'       => null,
			),
			'invalid payment type'   => array(
				'data'                 => array( 'type' => 'credit_card' ),
				'expected_issue_count' => 1,
				'expected_issue'       => array(
					'code' => 'DATA_ERROR',
					'type' => 'INVALID_DATA',
				),
			),
			'missing required type'  => array(
				'data'                 => array(
					'token'    => 'EC-7U8939823K567',
					'payer_id' => 'PAYER123456789',
				),
				'expected_issue_count' => 1,
				'expected_issue'       => array(
					'code'  => 'DATA_ERROR',
					'type'  => 'MISSING_FIELD',
					'field' => 'type',
				),
			),
		);
	}
}
