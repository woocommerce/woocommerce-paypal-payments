<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

/**
 * When to use:
 * - Product ID doesn't exist in WooCommerce.
 * - Invalid or malformed item_id.
 */
class InvalidProduct extends ValidationIssue {
	protected const ISSUE_CODE = 'INVENTORY_ISSUE';
	protected const ISSUE_TYPE = 'INVALID_DATA';
}
