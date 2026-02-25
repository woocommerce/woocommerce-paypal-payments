<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Schema\Coupon
 */
class CouponTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return Coupon::class;
	}

	protected function get_valid_data(): array {
		return array(
			'code'   => 'SAVE10',
			'action' => 'apply',
		);
	}

	protected function get_expected_data(): array {
		return array(
			'code'   => 'SAVE10',
			'action' => 'APPLY',
		);
	}

	protected function get_data_types(): array {
		return array(
			'code'   => 'string',
			'action' => array( 'type' => 'string', 'valid' => 'apply' ),
		);
	}

	protected function mandatory_data(): array {
		return array(
			'code'   => 'SAVE10',
			'action' => 'APPLY',
		);
	}

	public function test_required_fields(): void {
		$this->assertRequiredField( 'code' );
		$this->assertRequiredField( 'action' );

		// Coupon has no optional fields - both fields are required.
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'code', 'SAVE10' );
		$this->assertWhitespaceTrimming( 'action', 'APPLY' );

		$this->assertFieldIsCaseSensitive( 'code', 'Save10' );
		$this->assertFieldNormalizesToUppercase( 'action', 'apply', 'APPLY' );
	}

	public function test_field_format_validation(): void {
		$this->assertFieldFormat( 'action', $this->get_coupon_action_test_cases() );
	}
}
