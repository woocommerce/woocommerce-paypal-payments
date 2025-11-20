<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorType;

/**
 * When to use:
 * - Shipping not available to a specified location.
 * - Regional restrictions apply.
 * - No shipping methods available for this address.
 */
class ShippingUnavailable extends ValidationIssue {
	protected const ISSUE_CODE = ErrorCode::SHIPPING_ERROR;
	protected const ISSUE_TYPE = ErrorType::BUSINESS_RULE;
}
