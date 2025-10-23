<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

/**
 * When to use:
 * - Shipping not available to a specified location.
 * - Regional restrictions apply.
 * - No shipping methods available for this address.
 */
class ShippingUnavailable extends ValidationIssue {
	protected const ISSUE_CODE = 'SHIPPING_ERROR';
	protected const ISSUE_TYPE = 'BUSINESS_RULE';
}
