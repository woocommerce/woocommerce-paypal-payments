<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Enums;

/**
 * Specific pricing-related issue codes.
 *
 * Used in the context.specific_issue field when the main error code
 * is PRICING_ERROR.
 */
class PricingIssue {
	public const PRICE_MISMATCH                = 'PRICE_MISMATCH';
	public const DISCOUNT_EXPIRED              = 'DISCOUNT_EXPIRED';
	public const CURRENCY_MISMATCH             = 'CURRENCY_MISMATCH';
	public const DISCOUNT_USAGE_LIMIT_EXCEEDED = 'DISCOUNT_USAGE_LIMIT_EXCEEDED';
	public const DISCOUNT_CUSTOMER_INELIGIBLE  = 'DISCOUNT_CUSTOMER_INELIGIBLE';
	public const DISCOUNT_MINIMUM_NOT_MET      = 'DISCOUNT_MINIMUM_NOT_MET';
	public const TAX_CALCULATION_FAILED        = 'TAX_CALCULATION_FAILED';
	public const CURRENCY_NOT_SUPPORTED        = 'CURRENCY_NOT_SUPPORTED';
	public const PROMOTIONAL_CONFLICT          = 'PROMOTIONAL_CONFLICT';

	public static function get_all(): array {
		return array(
			self::PRICE_MISMATCH,
			self::DISCOUNT_EXPIRED,
			self::CURRENCY_MISMATCH,
			self::DISCOUNT_USAGE_LIMIT_EXCEEDED,
			self::DISCOUNT_CUSTOMER_INELIGIBLE,
			self::DISCOUNT_MINIMUM_NOT_MET,
			self::TAX_CALCULATION_FAILED,
			self::CURRENCY_NOT_SUPPORTED,
			self::PROMOTIONAL_CONFLICT,
		);
	}

	public static function is_valid( string $issue ): bool {
		return in_array( $issue, self::get_all(), true );
	}
}
