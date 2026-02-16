<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
/**
 * When to use:
 * - Product ID doesn't exist in WooCommerce.
 * - Invalid or malformed item_id.
 */
class InvalidProduct extends \WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidData
{
    protected const ISSUE_CODE = ErrorCode::INVENTORY_ISSUE;
}
