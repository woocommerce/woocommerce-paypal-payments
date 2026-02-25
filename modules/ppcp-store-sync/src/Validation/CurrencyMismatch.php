<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;

/**
 * When to use:
 * - Cart items have different currencies (mixed currency not supported).
 * - Cart currency does not match WooCommerce store currency.
 */
class CurrencyMismatch extends BusinessRuleViolation {
	protected const ISSUE_CODE = ErrorCode::PRICING_ERROR;
}
