<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorType;

/**
 * When to use:
 * - Shipping address cannot be validated.
 * - Address is incomplete or malformed.
 * - Postal code format is invalid.
 */
class InvalidAddress extends ValidationIssue {
	protected const ISSUE_CODE = ErrorCode::SHIPPING_ERROR;
	protected const ISSUE_TYPE = ErrorType::INVALID_DATA;
}
