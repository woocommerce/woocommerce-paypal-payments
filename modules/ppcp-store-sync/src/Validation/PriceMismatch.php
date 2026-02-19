<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;

/**
 * When to use:
 * - Product price does not match the cart value.
 * - Promotional pricing ended.
 * - Dynamic pricing adjustments occurred.
 */
class PriceMismatch extends BusinessRuleViolation {
	protected const ISSUE_CODE = ErrorCode::PRICING_ERROR;
}
