<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Enums;

/**
 * Specific business rule violation issue codes.
 *
 * Used in the context.specific_issue field when the main error code
 * is BUSINESS_RULE_ERROR.
 */
class BusinessRuleIssue {
	public const MINIMUM_ORDER_NOT_MET             = 'MINIMUM_ORDER_NOT_MET';
	public const AGE_RESTRICTED_PRODUCT            = 'AGE_RESTRICTED_PRODUCT';
	public const PURCHASE_LIMIT_EXCEEDED           = 'PURCHASE_LIMIT_EXCEEDED';
	public const MINIMUM_QUANTITY_NOT_MET          = 'MINIMUM_QUANTITY_NOT_MET';
	public const MAXIMUM_QUANTITY_EXCEEDED         = 'MAXIMUM_QUANTITY_EXCEEDED';
	public const CART_LIMIT_EXCEEDED               = 'CART_LIMIT_EXCEEDED';
	public const CUSTOMER_ACCOUNT_SUSPENDED        = 'CUSTOMER_ACCOUNT_SUSPENDED';
	public const BULK_ORDER_APPROVAL_REQUIRED      = 'BULK_ORDER_APPROVAL_REQUIRED';
	public const STORE_TEMPORARILY_CLOSED          = 'STORE_TEMPORARILY_CLOSED';
	public const LOYALTY_PROGRAM_VALIDATION_FAILED = 'LOYALTY_PROGRAM_VALIDATION_FAILED';
	public const BUSINESS_HOURS_RESTRICTION        = 'BUSINESS_HOURS_RESTRICTION';
	public const PRODUCT_ARCHIVED                  = 'PRODUCT_ARCHIVED';
	public const SUBSCRIPTION_PRODUCT_ERROR        = 'SUBSCRIPTION_PRODUCT_ERROR';

	/**
	 * Get all available business rule issue codes.
	 *
	 * @return array<string>
	 */
	public static function get_all(): array {
		return array(
			self::MINIMUM_ORDER_NOT_MET,
			self::AGE_RESTRICTED_PRODUCT,
			self::PURCHASE_LIMIT_EXCEEDED,
			self::MINIMUM_QUANTITY_NOT_MET,
			self::MAXIMUM_QUANTITY_EXCEEDED,
			self::CART_LIMIT_EXCEEDED,
			self::CUSTOMER_ACCOUNT_SUSPENDED,
			self::BULK_ORDER_APPROVAL_REQUIRED,
			self::STORE_TEMPORARILY_CLOSED,
			self::LOYALTY_PROGRAM_VALIDATION_FAILED,
			self::BUSINESS_HOURS_RESTRICTION,
			self::PRODUCT_ARCHIVED,
			self::SUBSCRIPTION_PRODUCT_ERROR,
		);
	}

	/**
	 * Check if business rule issue code is valid.
	 *
	 * @param string $issue Issue code to validate.
	 * @return bool
	 */
	public static function is_valid( string $issue ): bool {
		return in_array( $issue, self::get_all(), true );
	}
}
