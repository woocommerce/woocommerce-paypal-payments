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
	 * Tests that to_array includes all fields when provided.
	 */
	public function test_to_array_with_all_fields(): void {
		$data   = array(
			'type'     => 'paypal',
			'token'    => 'EC-7U8939823K567',
			'payer_id' => 'PAYER123456789',
		);
		$method = PaymentMethod::from_array( $data );
		$result = $method->to_array();

		$this->assertSame( 'paypal', $result['type'] );
		$this->assertSame( 'EC-7U8939823K567', $result['token'] );
		$this->assertSame( 'PAYER123456789', $result['payer_id'] );
	}

	/**
	 * Tests that invalid payment type creates validation issue.
	 */
	public function test_invalid_payment_type_creates_validation_issue(): void {
		$data   = array( 'type' => 'credit_card' );
		$method = PaymentMethod::from_array( $data );

		$issues = $method->validate();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'DATA_ERROR', $issues[0]->to_array()['code'] );
		$this->assertSame( 'INVALID_DATA', $issues[0]->to_array()['type'] );
	}

	/**
	 * Tests that valid PaymentMethod has no validation issues.
	 */
	public function test_valid_payment_method_has_no_issues(): void {
		$data   = array(
			'type'     => 'paypal',
			'token'    => 'EC-7U8939823K567',
			'payer_id' => 'PAYER123456789',
		);
		$method = PaymentMethod::from_array( $data );

		$issues = $method->validate();

		$this->assertEmpty( $issues );
	}

	/**
	 * Tests that missing required type field creates validation issue.
	 */
	public function test_missing_required_type_creates_validation_issue(): void {
		$data   = array(
			'token'    => 'EC-7U8939823K567',
			'payer_id' => 'PAYER123456789',
		);
		$method = PaymentMethod::from_array( $data );

		$issues = $method->validate();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'DATA_ERROR', $issues[0]->to_array()['code'] );
		$this->assertSame( 'MISSING_FIELD', $issues[0]->to_array()['type'] );
		$this->assertSame( 'type', $issues[0]->to_array()['field'] );
	}
}
