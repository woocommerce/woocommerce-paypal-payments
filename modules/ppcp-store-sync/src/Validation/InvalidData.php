<?php
// phpcs:disable Squiz.PHP.CommentedOutCode.Found

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorType;

/**
 * A generic invalid-data-issue, intended as a base class for
 * more specific issues or third party code.
 *
 * When to use:
 * - Provided data is incorrect, e.g., malformed email.
 * - Unexpected data format, e.g., non-numeric price.
 */
class InvalidData extends ValidationIssue {
	protected const ISSUE_TYPE = ErrorType::INVALID_DATA;
	protected const ISSUE_CODE = ErrorCode::DATA_ERROR;
}
