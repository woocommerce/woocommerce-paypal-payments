<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

/**
 * When to use:
 * - Product is currently unavailable.
 * - No stock remaining.
 * - Item temporarily out of inventory.
 */
class ItemOutOfStock extends ValidationIssue {
	protected const ISSUE_CODE = 'INVENTORY_ISSUE';
	protected const ISSUE_TYPE = 'BUSINESS_RULE';
}
