<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Enums;

/**
 * Main error codes for PayPal Cart API validation issues.
 *
 * These represent the top-level error categories that can occur
 * during cart operations and validation.
 */
class ErrorCode
{
    public const INVENTORY_ISSUE = 'INVENTORY_ISSUE';
    public const PRICING_ERROR = 'PRICING_ERROR';
    public const SHIPPING_ERROR = 'SHIPPING_ERROR';
    public const DATA_ERROR = 'DATA_ERROR';
    public const BUSINESS_RULE_ERROR = 'BUSINESS_RULE_ERROR';
    public const PAYMENT_ERROR = 'PAYMENT_ERROR';
}
