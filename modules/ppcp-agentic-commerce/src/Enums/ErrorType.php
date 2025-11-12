<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Enums;

/**
 * Error types for categorizing validation issues.
 */
class ErrorType {
	public const BUSINESS_RULE = 'BUSINESS_RULE';
	public const INVALID_DATA  = 'INVALID_DATA';
	public const MISSING_FIELD = 'MISSING_FIELD';
	public const SYSTEM_ERROR  = 'SYSTEM_ERROR';

	/**
	 * Get all available error types.
	 *
	 * @return array<string>
	 */
	public static function get_all(): array {
		return array(
			self::BUSINESS_RULE,
			self::INVALID_DATA,
			self::MISSING_FIELD,
			self::SYSTEM_ERROR,
		);
	}

	/**
	 * Check if error type is valid.
	 *
	 * @param string $type Error type to validate.
	 * @return bool
	 */
	public static function is_valid( string $type ): bool {
		return in_array( $type, self::get_all(), true );
	}
}
