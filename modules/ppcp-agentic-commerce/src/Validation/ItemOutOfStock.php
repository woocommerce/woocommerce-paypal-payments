<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;

/**
 * When to use:
 * - Product is currently unavailable.
 * - No stock remaining.
 * - Item temporarily out of inventory.
 */
class ItemOutOfStock extends BusinessRuleViolation {
	protected const ISSUE_CODE = ErrorCode::INVENTORY_ISSUE;
}
