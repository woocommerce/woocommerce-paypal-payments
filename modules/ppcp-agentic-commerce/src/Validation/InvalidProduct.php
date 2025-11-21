<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorType;

/**
 * When to use:
 * - Product ID doesn't exist in WooCommerce.
 * - Invalid or malformed item_id.
 */
class InvalidProduct extends ValidationIssue {
	protected const ISSUE_CODE = ErrorCode::INVENTORY_ISSUE;
	protected const ISSUE_TYPE = ErrorType::INVALID_DATA;
}
