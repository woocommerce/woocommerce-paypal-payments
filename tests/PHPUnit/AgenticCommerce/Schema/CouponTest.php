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
}

