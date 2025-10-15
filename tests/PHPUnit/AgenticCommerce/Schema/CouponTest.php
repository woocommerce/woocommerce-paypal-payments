<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidData;

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

	/**
	 * @dataProvider action_values_provider
	 */
	public function test_action_values( $action, bool $is_valid ): void {
		$data   = array(
			'code'   => 'SAVE10',
			'action' => $action,
		);
		$coupon = Coupon::from_array( $data );
		$issues = $coupon->validate();

		if ( $is_valid ) {
			$this->assertSame( strtoupper( $action ), $coupon->action() );
			$this->assertEmpty( $issues );
		} else {
			$this->assertSame( '', $coupon->action() );
			$this->assertCount( 1, $issues );
		}
	}

	public function action_values_provider(): array {
		return array(
			'apply'        => array( 'APPLY', true ),
			'remove'       => array( 'REMOVE', true ),
			'apply lower'  => array( 'apply', true ),
			'remove mixed' => array( 'ReMoVe', true ),
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

