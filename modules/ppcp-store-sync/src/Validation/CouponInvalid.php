<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;

/**
 * When to use:
 * - Coupon code is invalid or expired.
 * - Coupon not applicable to cart items.
 * - Coupon usage limit reached.
 */
class CouponInvalid extends BusinessRuleViolation {
	protected const ISSUE_CODE = ErrorCode::PRICING_ERROR;
}
