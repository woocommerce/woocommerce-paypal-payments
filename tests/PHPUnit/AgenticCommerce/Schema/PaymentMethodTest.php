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
			'payer_id' => 'merchant@example.com#123',
		);
	}

	protected function get_expected_data(): array {
		return array(
			'type'     => 'paypal',
			'token'    => 'EC123456789',
			'payer_id' => 'merchant@example.com#123',
		);
	}

	protected function get_data_types(): array {
		return array(
			'type'     => array( 'type' => 'string', 'valid' => 'paypal', 'default' => 'paypal' ),
			'token'    => 'string',
			'payer_id' => 'string',
		);
	}

	protected function mandatory_data(): array {
		return array(
			'type' => 'paypal',
		);
	}

	public function test_required_fields(): void {
		$this->assertRequiredField( 'type' );

		$this->assertOptionalField( 'token' );
		$this->assertOptionalField( 'payer_id' );
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'type', 'paypal' );
		$this->assertWhitespaceTrimming( 'token', 'EC-123' );
		$this->assertWhitespaceTrimming( 'payer_id', 'PAYER123' );

		$this->assertEmptyStringPreserved( 'token' );
		$this->assertEmptyStringPreserved( 'payer_id' );

		$this->assertFieldIsCaseSensitive( 'token', 'sample' );
		$this->assertFieldIsCaseSensitive( 'payer_id', 'sample' );
		$this->assertFieldAcceptsSpecialCharacters( 'token' );
		$this->assertFieldAcceptsSpecialCharacters( 'payer_id' );
	}
}
