<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Enums;

/**
 * Specific issue codes for pricing-related validation contexts.
 *
 * Used as the value of the SPECIFIC_ISSUE constant on PricingErrorContext subclasses.
 */
class ContextPricingIssue
{
    public const PRICE_MISMATCH = 'PRICE_MISMATCH';
    public const DISCOUNT_EXPIRED = 'DISCOUNT_EXPIRED';
    public const DISCOUNT_USAGE_LIMIT_EXCEEDED = 'DISCOUNT_USAGE_LIMIT_EXCEEDED';
    public const DISCOUNT_CUSTOMER_INELIGIBLE = 'DISCOUNT_CUSTOMER_INELIGIBLE';
    public const DISCOUNT_MINIMUM_NOT_MET = 'DISCOUNT_MINIMUM_NOT_MET';
    public const TAX_CALCULATION_FAILED = 'TAX_CALCULATION_FAILED';
    public const CURRENCY_NOT_SUPPORTED = 'CURRENCY_NOT_SUPPORTED';
    public const CURRENCY_MISMATCH = 'CURRENCY_MISMATCH';
    public const PROMOTIONAL_CONFLICT = 'PROMOTIONAL_CONFLICT';
}
