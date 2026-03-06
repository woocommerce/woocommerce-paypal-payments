<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

/**
 * Base class for all validation issue contexts.
 *
 * Concrete context classes must extend one of the six category subclasses
 * and define the SPECIFIC_ISSUE constant using the matching Context*Issue enum.
 */
abstract class IssueContext {
	/**
	 * The specific issue code for this context.
	 *
	 * Concrete subclasses must override this constant using the appropriate
	 * Context*Issue enum value (e.g. ContextPricingIssue::PRICE_MISMATCH).
	 */
	protected const SPECIFIC_ISSUE = '';

	final private function __construct() {
	}

	/**
	 * Creates a new context instance.
	 *
	 * @return static
	 */
	public static function create(): self {
		return new static();
	}

	public function to_array(): array {
		return array(
			'specific_issue' => static::SPECIFIC_ISSUE,
		);
	}

	/**
	 * Formats a value as an ISO 8601 date-time string.
	 */
	protected function format_date_time( ?int $value ): ?string {
		if ( is_int( $value ) ) {
			return gmdate( 'Y-m-d\TH:i:s\Z', $value );
		}

		return null;
	}

	/**
	 * Sanitizes an array to contain only string values.
	 * Numeric values are cast to string; all other non-string types are stripped.
	 * Returns null when the input is null or no string values remain.
	 */
	protected function sanitize_string_array( ?array $values ): ?array {
		if ( empty( $values ) ) {
			return null;
		}

		$result = array();
		foreach ( $values as $value ) {
			if ( is_string( $value ) ) {
				$result[] = $value;
			} elseif ( is_numeric( $value ) ) {
				$result[] = (string) $value;
			}
		}

		return $result !== array() ? $result : null;
	}
}
