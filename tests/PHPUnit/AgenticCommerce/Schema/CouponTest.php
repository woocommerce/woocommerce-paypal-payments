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
}

