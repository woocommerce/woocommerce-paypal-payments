<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Enums;

/**
 * Specific data validation issue codes.
 *
 * Used in the context.specific_issue field when the main error code
 * is DATA_ERROR.
 */
class DataIssue {
	public const MISSING_CHECKOUT_FIELDS   = 'MISSING_CHECKOUT_FIELDS';
	public const INVALID_EMAIL_FORMAT      = 'INVALID_EMAIL_FORMAT';
	public const MISSING_PAYMENT_METHOD    = 'MISSING_PAYMENT_METHOD';
	public const MISSING_POLICY_ACCEPTANCE = 'MISSING_POLICY_ACCEPTANCE';
	public const REQUIRED_FIELD_MISSING    = 'REQUIRED_FIELD_MISSING';
	public const INVALID_PHONE_FORMAT      = 'INVALID_PHONE_FORMAT';
	public const FIELD_VALUE_TOO_LONG      = 'FIELD_VALUE_TOO_LONG';
	public const FIELD_VALUE_TOO_SHORT     = 'FIELD_VALUE_TOO_SHORT';
	public const INVALID_DATE_FORMAT       = 'INVALID_DATE_FORMAT';
	public const FUTURE_DATE_NOT_ALLOWED   = 'FUTURE_DATE_NOT_ALLOWED';
	public const INVALID_CUSTOMER_DATA     = 'INVALID_CUSTOMER_DATA';
	public const ITEM_NOT_FOUND            = 'ITEM_NOT_FOUND';
	public const INVALID_ITEM_DATA         = 'INVALID_ITEM_DATA';
	public const ITEM_ATTRIBUTE_MISMATCH   = 'ITEM_ATTRIBUTE_MISMATCH';

	public static function get_all(): array {
		return array(
			self::MISSING_CHECKOUT_FIELDS,
			self::INVALID_EMAIL_FORMAT,
			self::MISSING_PAYMENT_METHOD,
			self::MISSING_POLICY_ACCEPTANCE,
			self::REQUIRED_FIELD_MISSING,
			self::INVALID_PHONE_FORMAT,
			self::FIELD_VALUE_TOO_LONG,
			self::FIELD_VALUE_TOO_SHORT,
			self::INVALID_DATE_FORMAT,
			self::FUTURE_DATE_NOT_ALLOWED,
			self::INVALID_CUSTOMER_DATA,
			self::ITEM_NOT_FOUND,
			self::INVALID_ITEM_DATA,
			self::ITEM_ATTRIBUTE_MISMATCH,
		);
	}

	public static function is_valid( string $issue ): bool {
		return in_array( $issue, self::get_all(), true );
	}
}
