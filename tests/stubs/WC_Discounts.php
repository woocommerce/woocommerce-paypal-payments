<?php
/**
 * Minimal WC_Discounts stub for unit tests.
 *
 * Ensures that CouponValidator::is_wc_available() returns true, allowing unit
 * tests to reach the coupon-disabled gate and beyond without requiring a real
 * WooCommerce installation.
 */

if ( ! class_exists( 'WC_Discounts' ) ) {
	class WC_Discounts {
		public function set_items( $items = array() ): void {
		}

		public function is_coupon_valid( $coupon ): bool {
			return true;
		}
	}
}
