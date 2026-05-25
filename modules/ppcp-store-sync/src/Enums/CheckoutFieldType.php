<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Enums;

/**
 * Checkout field types for missing field validation.
 *
 * Used when DATA_ERROR occurs with MISSING_CHECKOUT_FIELDS specific issue.
 */
class CheckoutFieldType
{
    public const AGE_VERIFICATION_18_PLUS = 'AGE_VERIFICATION_18_PLUS';
    public const AGE_VERIFICATION_21_PLUS = 'AGE_VERIFICATION_21_PLUS';
    public const GIFT_RECIPIENT_EMAIL = 'GIFT_RECIPIENT_EMAIL';
    public const GIFT_RECIPIENT_NAME = 'GIFT_RECIPIENT_NAME';
    public const GIFT_MESSAGE = 'GIFT_MESSAGE';
    public const DELIVERY_INSTRUCTIONS = 'DELIVERY_INSTRUCTIONS';
    public const DELIVERY_DATE_PREFERENCE = 'DELIVERY_DATE_PREFERENCE';
    public const ALLERGY_INFORMATION = 'ALLERGY_INFORMATION';
    public const CUSTOM_ENGRAVING_TEXT = 'CUSTOM_ENGRAVING_TEXT';
    public const CUSTOM_SIZING_INFO = 'CUSTOM_SIZING_INFO';
    public const TERMS_ACCEPTANCE = 'TERMS_ACCEPTANCE';
    public const PRIVACY_CONSENT = 'PRIVACY_CONSENT';
}
