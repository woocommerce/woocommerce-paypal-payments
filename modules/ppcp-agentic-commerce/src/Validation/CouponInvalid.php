<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorType;

/**
 * When to use:
 * - Coupon code is invalid or expired.
 * - Coupon not applicable to cart items.
 * - Coupon usage limit reached.
 */
class CouponInvalid extends ValidationIssue {
	protected const ISSUE_CODE = ErrorCode::PRICING_ERROR;
	protected const ISSUE_TYPE = ErrorType::BUSINESS_RULE;
}
