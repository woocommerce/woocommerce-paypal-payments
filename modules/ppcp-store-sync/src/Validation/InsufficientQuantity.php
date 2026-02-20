<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;
/**
 * When to use:
 * - Requested quantity exceeds available stock.
 * - Stock reduced between cart creation and checkout.
 * - High-demand item with limited availability.
 */
class InsufficientQuantity extends \WooCommerce\PayPalCommerce\StoreSync\Validation\BusinessRuleViolation
{
    protected const ISSUE_CODE = ErrorCode::INVENTORY_ISSUE;
}
