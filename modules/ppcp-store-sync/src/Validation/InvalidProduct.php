<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;

/**
 * When to use:
 * - Product ID doesn't exist in WooCommerce.
 * - Invalid or malformed item_id.
 */
class InvalidProduct extends InvalidData {
	protected const ISSUE_CODE = ErrorCode::INVENTORY_ISSUE;
}
