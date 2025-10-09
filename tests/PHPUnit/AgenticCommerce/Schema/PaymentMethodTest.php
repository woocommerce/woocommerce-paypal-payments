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
}
