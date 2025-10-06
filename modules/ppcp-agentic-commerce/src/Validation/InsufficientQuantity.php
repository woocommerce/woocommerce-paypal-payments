<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

/**
 * When to use:
 * - Requested quantity exceeds available stock.
 * - Stock reduced between cart creation and checkout.
 * - High-demand item with limited availability.
 */
class InsufficientQuantity extends ValidationIssue {
	protected const ISSUE_CODE = 'INVENTORY_ISSUE';
	protected const ISSUE_TYPE = 'BUSINESS_RULE';
}
