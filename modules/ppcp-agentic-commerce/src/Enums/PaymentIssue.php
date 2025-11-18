<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Enums;

/**
 * Specific payment processing issue codes.
 *
 * Used in HTTP 422 errors from checkout endpoint, not in validation_issues.
 * These represent payment processing failures during transaction attempts.
 */
class PaymentIssue {
	public const PAYMENT_DECLINED              = 'PAYMENT_DECLINED';
	public const PAYMENT_AMOUNT_TOO_SMALL      = 'PAYMENT_AMOUNT_TOO_SMALL';
	public const PAYMENT_METHOD_NOT_ACCEPTED   = 'PAYMENT_METHOD_NOT_ACCEPTED';
	public const CURRENCY_CONVERSION_FAILED    = 'CURRENCY_CONVERSION_FAILED';
	public const PAYMENT_PROCESSOR_UNAVAILABLE = 'PAYMENT_PROCESSOR_UNAVAILABLE';
	public const MERCHANT_ACCOUNT_ISSUE        = 'MERCHANT_ACCOUNT_ISSUE';
	public const PAYMENT_INSUFFICIENT_FUNDS    = 'PAYMENT_INSUFFICIENT_FUNDS';
	public const PAYMENT_EXPIRED               = 'PAYMENT_EXPIRED';
	public const PAYMENT_FRAUD_DETECTED        = 'PAYMENT_FRAUD_DETECTED';

	public static function get_all(): array {
		return array(
			self::PAYMENT_DECLINED,
			self::PAYMENT_AMOUNT_TOO_SMALL,
			self::PAYMENT_METHOD_NOT_ACCEPTED,
			self::CURRENCY_CONVERSION_FAILED,
			self::PAYMENT_PROCESSOR_UNAVAILABLE,
			self::MERCHANT_ACCOUNT_ISSUE,
			self::PAYMENT_INSUFFICIENT_FUNDS,
			self::PAYMENT_EXPIRED,
			self::PAYMENT_FRAUD_DETECTED,
		);
	}

	public static function is_valid( string $issue ): bool {
		return in_array( $issue, self::get_all(), true );
	}
}
