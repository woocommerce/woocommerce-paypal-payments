<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;
/**
 * When to use:
 * - Product is currently unavailable.
 * - No stock remaining.
 * - Item temporarily out of inventory.
 */
class ItemOutOfStock extends \WooCommerce\PayPalCommerce\StoreSync\Validation\BusinessRuleViolation
{
    protected const ISSUE_CODE = ErrorCode::INVENTORY_ISSUE;
}
