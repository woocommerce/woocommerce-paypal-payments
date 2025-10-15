<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

/**
 * @covers Coupon
 */
class CouponTest extends SchemaTestCase {

	protected function get_schema_class(): string {
		return Coupon::class;
	}

	protected function get_valid_data(): array {
		return array(
			'code'   => 'SAVE10',
			'action' => 'APPLY',
		);
	}

	protected function get_expected_data(): array {
		return array(
			'code'   => 'SAVE10',
			'action' => 'APPLY',
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
	}

	public function test_optional_fields(): void {
		// Coupon has no optional fields - both fields are required.
		$this->addToAssertionCount( 1 );
	}

	public function test_string_fields(): void {
		$this->assertWhitespaceTrimming( 'code', 'SAVE10' );
		$this->assertWhitespaceTrimming( 'action', 'APPLY' );
	}

	public function test_field_format_validation(): void {
		$this->assertFieldFormat( 'action', $this->get_action_values() );
	}

	public function get_action_values(): array {
		return array(
			'valid apply'  => array( 'APPLY', true ),
			'remove'       => array( 'REMOVE', true ),
			'apply lower'  => array( 'apply', true, 'APPLY' ),
			'remove mixed' => array( 'ReMoVe', true, 'REMOVE' ),
			'invalid'      => array( 'INVALID', false ),
			'empty'        => array( '', false ),
		);
	}

	/**
	 * Tests that multiple validation issues are collected.
	 */
	public function test_multiple_validation_issues(): void {
		$data   = array(); // Both fields missing.
		$coupon = Coupon::from_array( $data );
		$issues = $coupon->validate();

		$this->assertCount( 2, $issues, 'Should collect all validation issues' );
	}
}

