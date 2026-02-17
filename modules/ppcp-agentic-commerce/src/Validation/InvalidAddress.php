<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
/**
 * When to use:
 * - Shipping address cannot be validated.
 * - Address is incomplete or malformed.
 * - Postal code format is invalid.
 */
class InvalidAddress extends \WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidData
{
    protected const ISSUE_CODE = ErrorCode::SHIPPING_ERROR;
}
