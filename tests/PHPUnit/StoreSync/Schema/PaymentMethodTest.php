<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Schema\PaymentMethod
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

	// -------------------------------------------------------------------------
	// Group — to_array()
	// -------------------------------------------------------------------------

	/**
	 * GIVEN a PaymentMethod with token and payer_id set
	 * WHEN to_array() is called
	 * THEN all three fields (type, token, payer_id) appear in the result
	 * AND type is always 'paypal'
	 */
	public function test_to_array_includes_all_fields_when_token_and_payer_id_are_present(): void {
		$method = PaymentMethod::from_array(
			array( 'type' => 'paypal', 'token' => 'EC-123456', 'payer_id' => 'PAYER-789' ),
			new \WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation()
		);

		$result = $method->to_array();

		$this->assertSame( 'paypal', $result['type'] );
		$this->assertSame( 'EC-123456', $result['token'] );
		$this->assertSame( 'PAYER-789', $result['payer_id'] );
	}

	/**
	 * GIVEN a PaymentMethod with only the mandatory type field
	 * WHEN to_array() is called
	 * THEN only type is present; token and payer_id are absent from the result
	 */
	public function test_to_array_omits_absent_optional_fields(): void {
		$method = PaymentMethod::from_array(
			array( 'type' => 'paypal' ),
			new \WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation()
		);

		$result = $method->to_array();

		$this->assertSame( 'paypal', $result['type'] );
		$this->assertArrayNotHasKey( 'token', $result );
		$this->assertArrayNotHasKey( 'payer_id', $result );
	}
}
