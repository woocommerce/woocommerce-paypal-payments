<?php
/**
 * Minimal WC_Coupon stub for unit tests.
 *
 * Ensures that CouponValidator::is_wc_available() returns true, allowing unit
 * tests to reach the coupon-disabled gate and beyond without requiring a real
 * WooCommerce installation.
 */

if ( ! class_exists( 'WC_Coupon' ) ) {
	class WC_Coupon {
		private string $code;

		public function __construct( $code = '' ) {
			$this->code = (string) $code;
		}

		public function get_id(): int {
			return 0;
		}

		public function get_code(): string {
			return $this->code;
		}

		public function get_individual_use(): bool {
			return false;
		}

		public function get_description(): string {
			return '';
		}

		public function get_discount_type(): string {
			return '';
		}
	}
}
