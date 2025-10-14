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
	 * Tests that Coupon correctly stores and returns the code.
	 *
	 * @dataProvider code_provider
	 */
	public function test_code_accessor( string $code ): void {
		$data   = array(
			'code'   => $code,
			'action' => 'APPLY',
		);
		$coupon = Coupon::from_array( $data );

		$this->assertSame( $code, $coupon->code() );
	}

	public function code_provider(): array {
		return array(
			'save10'   => array( 'SAVE10' ),
			'summer20' => array( 'SUMMER20' ),
			'welcome'  => array( 'WELCOME' ),
		);
	}

	/**
	 * Tests that Coupon correctly stores and returns valid action values.
	 *
	 * @dataProvider valid_action_provider
	 */
	public function test_action_accessor( string $action ): void {
		$data   = array(
			'code'   => 'SAVE10',
			'action' => $action,
		);
		$coupon = Coupon::from_array( $data );

		$this->assertSame( $action, $coupon->action() );
		$this->assertEmpty( $coupon->validate() );
	}

	public function valid_action_provider(): array {
		return array(
			'apply'  => array( 'APPLY' ),
			'remove' => array( 'REMOVE' ),
		);
	}

	/**
	 * Tests that invalid action values produce validation issues.
	 */
	public function test_invalid_action(): void {
		$data   = array(
			'code'   => 'SAVE10',
			'action' => 'INVALID',
		);
		$coupon = Coupon::from_array( $data );
		$issues = $coupon->validate();

		$this->assertCount( 1, $issues );

		$issue_data = $issues[0]->to_array();
		$this->assertSame( 'action', $issue_data['field'] );
		$this->assertInstanceOf( InvalidData::class, $issues[0] );
	}

	/**
	 * Tests that missing code produces validation issue.
	 */
	public function test_missing_code(): void {
		$data   = array( 'action' => 'APPLY' );
		$coupon = Coupon::from_array( $data );
		$issues = $coupon->validate();

		$this->assertCount( 1, $issues );

		$issue_data = $issues[0]->to_array();
		$this->assertSame( 'code', $issue_data['field'] );
		$this->assertInstanceOf( InvalidData::class, $issues[0] );
	}

	/**
	 * Tests that missing action produces validation issue.
	 */
	public function test_missing_action(): void {
		$data   = array( 'code' => 'SAVE10' );
		$coupon = Coupon::from_array( $data );
		$issues = $coupon->validate();

		$this->assertCount( 1, $issues );

		$issue_data = $issues[0]->to_array();
		$this->assertSame( 'action', $issue_data['field'] );
		$this->assertInstanceOf( InvalidData::class, $issues[0] );
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

