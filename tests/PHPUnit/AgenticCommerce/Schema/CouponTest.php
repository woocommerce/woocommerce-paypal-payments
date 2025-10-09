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

	/**
	 * Tests that Coupon stores and returns the code.
	 */
	public function test_code_accessor(): void {
		$data   = array(
			'code'   => 'SUMMER20',
			'action' => 'APPLY',
		);
		$coupon = Coupon::from_array( $data );

		$this->assertSame( 'SUMMER20', $coupon->code() );
	}

	/**
	 * Tests that Coupon stores and returns the action.
	 */
	public function test_action_accessor(): void {
		$data   = array(
			'code'   => 'SAVE10',
			'action' => 'REMOVE',
		);
		$coupon = Coupon::from_array( $data );

		$this->assertSame( 'REMOVE', $coupon->action() );
	}

	/**
	 * Tests that Coupon returns the actual code from input data.
	 */
	public function test_code_returns_actual_input(): void {
		$data   = array(
			'code'   => 'SAVE10',
			'action' => 'APPLY',
		);
		$coupon = Coupon::from_array( $data );

		$this->assertSame( 'SAVE10', $coupon->code() );
	}

	/**
	 * Tests that Coupon validation rejects invalid action values.
	 */
	public function test_validation_rejects_invalid_action(): void {
		$data   = array(
			'code'   => 'SAVE10',
			'action' => 'INVALID',
		);
		$coupon = Coupon::from_array( $data );

		$this->assertCount( 1, $coupon->validate() );
	}

	/**
	 * Tests that missing code produces validation issue.
	 */
	public function test_missing_code(): void {
		$data   = array( 'action' => 'APPLY' );
		$coupon = Coupon::from_array( $data );
		$issues = $coupon->validate();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'code', $issues[0]->to_array()['field'] );
	}

	/**
	 * Tests that missing action produces validation issue.
	 */
	public function test_missing_action(): void {
		$data   = array( 'code' => 'SAVE10' );
		$coupon = Coupon::from_array( $data );
		$issues = $coupon->validate();

		$this->assertCount( 1, $issues );
		$this->assertSame( 'action', $issues[0]->to_array()['field'] );
	}

	/**
	 * Tests that valid action values pass validation.
	 *
	 * @dataProvider valid_action_provider
	 */
	public function test_valid_action_values( string $action ): void {
		$data   = array(
			'code'   => 'SAVE10',
			'action' => $action,
		);
		$coupon = Coupon::from_array( $data );

		$this->assertEmpty( $coupon->validate() );
	}

	public function valid_action_provider(): array {
		return array(
			'apply'  => array( 'APPLY' ),
			'remove' => array( 'REMOVE' ),
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

