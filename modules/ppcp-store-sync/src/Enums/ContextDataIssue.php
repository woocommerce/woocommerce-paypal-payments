<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Enums;

/**
 * Specific issue codes for data-related validation contexts.
 *
 * Used as the value of the SPECIFIC_ISSUE constant on DataErrorContext subclasses.
 */
class ContextDataIssue
{
    public const MISSING_CHECKOUT_FIELDS = 'MISSING_CHECKOUT_FIELDS';
    public const MISSING_PAYMENT_METHOD = 'MISSING_PAYMENT_METHOD';
    public const MISSING_POLICY_ACCEPTANCE = 'MISSING_POLICY_ACCEPTANCE';
    public const REQUIRED_FIELD_MISSING = 'REQUIRED_FIELD_MISSING';
    public const INVALID_EMAIL_FORMAT = 'INVALID_EMAIL_FORMAT';
    public const INVALID_PHONE_FORMAT = 'INVALID_PHONE_FORMAT';
    public const FIELD_VALUE_TOO_LONG = 'FIELD_VALUE_TOO_LONG';
    public const FIELD_VALUE_TOO_SHORT = 'FIELD_VALUE_TOO_SHORT';
    public const INVALID_DATE_FORMAT = 'INVALID_DATE_FORMAT';
    public const FUTURE_DATE_NOT_ALLOWED = 'FUTURE_DATE_NOT_ALLOWED';
    public const INVALID_CUSTOMER_DATA = 'INVALID_CUSTOMER_DATA';
    public const ITEM_NOT_FOUND = 'ITEM_NOT_FOUND';
    public const INVALID_ITEM_DATA = 'INVALID_ITEM_DATA';
    public const ITEM_ATTRIBUTE_MISMATCH = 'ITEM_ATTRIBUTE_MISMATCH';
}
