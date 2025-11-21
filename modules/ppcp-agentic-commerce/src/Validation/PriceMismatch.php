<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorType;

/**
 * When to use:
 * - Product price does not match the cart value.
 * - Promotional pricing ended.
 * - Dynamic pricing adjustments occurred.
 */
class PriceMismatch extends ValidationIssue {
	protected const ISSUE_CODE = ErrorCode::PRICING_ERROR;
	protected const ISSUE_TYPE = ErrorType::BUSINESS_RULE;
}
