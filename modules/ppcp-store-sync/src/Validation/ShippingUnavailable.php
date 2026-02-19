<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;

/**
 * When to use:
 * - Shipping not available to a specified location.
 * - Regional restrictions apply.
 * - No shipping methods available for this address.
 */
class ShippingUnavailable extends BusinessRuleViolation {
	protected const ISSUE_CODE = ErrorCode::SHIPPING_ERROR;
}
