<?php
/**
 * Stub for the third-party Kestrel\Account_Funds\Cart class.
 *
 * Loaded on demand (never at file-parse time) so tests can also exercise the
 * genuine "plugin not installed" path via class_exists().
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */

declare(strict_types=1);

namespace Kestrel\Account_Funds;

if ( ! class_exists( Cart::class ) ) {
	class Cart {

		/**
		 * @var bool
		 */
		public static $is_using_partially = false;

		/**
		 * @var float
		 */
		public static $applied_amount = 0.0;

		public static function reset(): void {
			self::$is_using_partially = false;
			self::$applied_amount     = 0.0;
		}

		public static function is_using_account_funds_partially(): bool {
			return self::$is_using_partially;
		}

		public static function get_applied_account_funds_amount(): float {
			return self::$applied_amount;
		}
	}
}
