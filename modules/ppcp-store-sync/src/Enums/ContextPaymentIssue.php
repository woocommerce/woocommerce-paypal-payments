<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Enums;

/**
 * Specific issue codes for payment-related validation contexts.
 *
 * Used as the value of the SPECIFIC_ISSUE constant on PaymentErrorContext subclasses.
 */
class ContextPaymentIssue
{
    public const PAYMENT_AMOUNT_TOO_LARGE = 'PAYMENT_AMOUNT_TOO_LARGE';
    public const PAYMENT_AMOUNT_TOO_SMALL = 'PAYMENT_AMOUNT_TOO_SMALL';
    public const PAYMENT_METHOD_NOT_ACCEPTED = 'PAYMENT_METHOD_NOT_ACCEPTED';
    public const CURRENCY_CONVERSION_FAILED = 'CURRENCY_CONVERSION_FAILED';
    public const PAYMENT_PROCESSOR_UNAVAILABLE = 'PAYMENT_PROCESSOR_UNAVAILABLE';
    public const MERCHANT_ACCOUNT_ISSUE = 'MERCHANT_ACCOUNT_ISSUE';
    public const PAYMENT_DECLINED = 'PAYMENT_DECLINED';
    public const PAYMENT_INSUFFICIENT_FUNDS = 'PAYMENT_INSUFFICIENT_FUNDS';
    public const PAYMENT_EXPIRED = 'PAYMENT_EXPIRED';
    public const PAYMENT_FRAUD_DETECTED = 'PAYMENT_FRAUD_DETECTED';
}
