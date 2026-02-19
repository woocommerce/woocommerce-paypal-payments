<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;

/**
 * When to use:
 * - Shipping address cannot be validated.
 * - Address is incomplete or malformed.
 * - Postal code format is invalid.
 */
class InvalidAddress extends InvalidData {
	protected const ISSUE_CODE = ErrorCode::SHIPPING_ERROR;
}
