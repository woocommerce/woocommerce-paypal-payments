<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Enums;

/**
 * Error types for categorizing validation issues.
 */
class ErrorType
{
    public const BUSINESS_RULE = 'BUSINESS_RULE';
    public const INVALID_DATA = 'INVALID_DATA';
    public const MISSING_FIELD = 'MISSING_FIELD';
    public const SYSTEM_ERROR = 'SYSTEM_ERROR';
}
