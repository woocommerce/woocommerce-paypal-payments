<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

/**
 * When to use:
 * - Product price does not match the cart value.
 * - Promotional pricing ended.
 * - Dynamic pricing adjustments occurred.
 */
class PriceMismatch extends ValidationIssue {
	protected const ISSUE_CODE = 'PRICING_ERROR';
	protected const ISSUE_TYPE = 'BUSINESS_RULE';
}
