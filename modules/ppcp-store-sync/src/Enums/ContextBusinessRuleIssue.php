<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Enums;

/**
 * Specific issue codes for business-rule-related validation contexts.
 *
 * Used as the value of the SPECIFIC_ISSUE constant on BusinessRuleErrorContext subclasses.
 */
class ContextBusinessRuleIssue
{
    public const MINIMUM_ORDER_NOT_MET = 'MINIMUM_ORDER_NOT_MET';
    public const MINIMUM_QUANTITY_NOT_MET = 'MINIMUM_QUANTITY_NOT_MET';
    public const MAXIMUM_QUANTITY_EXCEEDED = 'MAXIMUM_QUANTITY_EXCEEDED';
    public const CART_LIMIT_EXCEEDED = 'CART_LIMIT_EXCEEDED';
    public const CUSTOMER_ACCOUNT_SUSPENDED = 'CUSTOMER_ACCOUNT_SUSPENDED';
    public const PURCHASE_LIMIT_EXCEEDED = 'PURCHASE_LIMIT_EXCEEDED';
    public const BULK_ORDER_APPROVAL_REQUIRED = 'BULK_ORDER_APPROVAL_REQUIRED';
    public const STORE_TEMPORARILY_CLOSED = 'STORE_TEMPORARILY_CLOSED';
    public const AGE_RESTRICTED_PRODUCT = 'AGE_RESTRICTED_PRODUCT';
    public const LOYALTY_PROGRAM_VALIDATION_FAILED = 'LOYALTY_PROGRAM_VALIDATION_FAILED';
    public const BUSINESS_HOURS_RESTRICTION = 'BUSINESS_HOURS_RESTRICTION';
    public const PRODUCT_ARCHIVED = 'PRODUCT_ARCHIVED';
}
