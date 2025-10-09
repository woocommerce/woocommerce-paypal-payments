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
	 * Tests that PaymentMethod stores and returns the type.
	 */
	public function test_type_accessor(): void {
		$data   = array( 'type' => 'paypal' );
		$method = PaymentMethod::from_array( $data );

		$this->assertSame( 'paypal', $method->type() );
	}

	/**
	 * Tests that PaymentMethod stores and returns the optional token.
	 */
	public function test_token_accessor(): void {
		$data   = array(
			'type'  => 'paypal',
			'token' => 'EC-7U8939823K567',
		);
		$method = PaymentMethod::from_array( $data );

		$this->assertSame( 'EC-7U8939823K567', $method->token() );
	}

	/**
	 * Tests that PaymentMethod stores and returns the optional payer_id.
	 */
	public function test_payer_id_accessor(): void {
		$data   = array(
			'type'     => 'paypal',
			'payer_id' => 'PAYER123456789',
		);
		$method = PaymentMethod::from_array( $data );

		$this->assertSame( 'PAYER123456789', $method->payer_id() );
	}

	/**
	 * Tests that PaymentMethod works with all fields together.
	 */
	public function test_all_fields_together(): void {
		$data   = array(
			'type'     => 'paypal',
			'token'    => 'EC-7U8939823K567',
			'payer_id' => 'PAYER123456789',
		);
		$method = PaymentMethod::from_array( $data );

		$this->assertSame( 'paypal', $method->type() );
		$this->assertSame( 'EC-7U8939823K567', $method->token() );
		$this->assertSame( 'PAYER123456789', $method->payer_id() );
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
}
