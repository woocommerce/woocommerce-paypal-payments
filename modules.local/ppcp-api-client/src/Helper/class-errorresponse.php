<?php
/**
 * A Collection of all error responses for the order endpoint.
 *
 * @package Inpsyde\PayPalCommerce\ApiClient\Helper
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Helper;

/**
 * Class ErrorResponse
 */
class ErrorResponse {

	public const UNKNOWN = 'UNKNOWN';

	/* Order error codes */
	public const ACTION_DOES_NOT_MATCH_INTENT                 = 'ACTION_DOES_NOT_MATCH_INTENT';
	public const AGREEMENT_ALREADY_CANCELLED                  = 'AGREEMENT_ALREADY_CANCELLED';
	public const AMOUNT_CANNOT_BE_SPECIFIED                   = 'AMOUNT_CANNOT_BE_SPECIFIED';
	public const AMOUNT_MISMATCH                              = 'AMOUNT_MISMATCH';
	public const AMOUNT_NOT_PATCHABLE                         = 'AMOUNT_NOT_PATCHABLE';
	public const AUTH_CAPTURE_NOT_ENABLED                     = 'AUTH_CAPTURE_NOT_ENABLED';
	public const AUTHENTICATION_FAILURE                       = 'AUTHENTICATION_FAILURE';
	public const AUTHORIZATION_AMOUNT_EXCEEDED                = 'AUTHORIZATION_AMOUNT_EXCEEDED';
	public const AUTHORIZATION_CURRENCY_MISMATCH              = 'AUTHORIZATION_CURRENCY_MISMATCH';
	public const BILLING_AGREEMENT_NOT_FOUND                  = 'BILLING_AGREEMENT_NOT_FOUND';
	public const CANNOT_BE_NEGATIVE                           = 'CANNOT_BE_NEGATIVE';
	public const CANNOT_BE_ZERO_OR_NEGATIVE                   = 'CANNOT_BE_ZERO_OR_NEGATIVE';
	public const CARD_TYPE_NOT_SUPPORTED                      = 'CARD_TYPE_NOT_SUPPORTED';
	public const INVALID_SECURITY_CODE_LENGTH                 = 'INVALID_SECURITY_CODE_LENGTH';
	public const CITY_REQUIRED                                = 'CITY_REQUIRED';
	public const COMPLIANCE_VIOLATION                         = 'COMPLIANCE_VIOLATION';
	public const CONSENT_NEEDED                               = 'CONSENT_NEEDED';
	public const CURRENCY_NOT_SUPPORTED_FOR_CARD_TYPE         = 'CURRENCY_NOT_SUPPORTED_FOR_CARD_TYPE';
	public const CURRENCY_NOT_SUPPORTED_FOR_COUNTRY           = 'CURRENCY_NOT_SUPPORTED_FOR_COUNTRY';
	public const DECIMAL_PRECISION                            = 'DECIMAL_PRECISION';
	public const DOMESTIC_TRANSACTION_REQUIRED                = 'DOMESTIC_TRANSACTION_REQUIRED';
	public const DUPLICATE_INVOICE_ID                         = 'DUPLICATE_INVOICE_ID';
	public const DUPLICATE_REQUEST_ID                         = 'DUPLICATE_REQUEST_ID';
	public const FIELD_NOT_PATCHABLE                          = 'FIELD_NOT_PATCHABLE';
	public const INSTRUMENT_DECLINED                          = 'INSTRUMENT_DECLINED';
	public const INTERNAL_SERVER_ERROR                        = 'INTERNAL_SERVER_ERROR';
	public const INTERNAL_SERVICE_ERROR                       = 'INTERNAL_SERVICE_ERROR';
	public const INVALID_ACCOUNT_STATUS                       = 'INVALID_ACCOUNT_STATUS';
	public const INVALID_ARRAY_MAX_ITEMS                      = 'INVALID_ARRAY_MAX_ITEMS';
	public const INVALID_ARRAY_MIN_ITEMS                      = 'INVALID_ARRAY_MIN_ITEMS';
	public const INVALID_COUNTRY_CODE                         = 'INVALID_COUNTRY_CODE';
	public const INVALID_CURRENCY_CODE                        = 'INVALID_CURRENCY_CODE';
	public const INVALID_JSON_POINTER_FORMAT                  = 'INVALID_JSON_POINTER_FORMAT';
	public const INVALID_PARAMETER_SYNTAX                     = 'INVALID_PARAMETER_SYNTAX';
	public const INVALID_PARAMETER_VALUE                      = 'INVALID_PARAMETER_VALUE';
	public const INVALID_PARAMETER                            = 'INVALID_PARAMETER';
	public const INVALID_PATCH_OPERATION                      = 'INVALID_PATCH_OPERATION';
	public const INVALID_PAYER_ID                             = 'INVALID_PAYER_ID';
	public const INVALID_RESOURCE_ID                          = 'INVALID_RESOURCE_ID';
	public const INVALID_STRING_LENGTH                        = 'INVALID_STRING_LENGTH';
	public const ITEM_TOTAL_MISMATCH                          = 'ITEM_TOTAL_MISMATCH';
	public const ITEM_TOTAL_REQUIRED                          = 'ITEM_TOTAL_REQUIRED';
	public const MAX_AUTHORIZATION_COUNT_EXCEEDED             = 'MAX_AUTHORIZATION_COUNT_EXCEEDED';
	public const MAX_NUMBER_OF_PAYMENT_ATTEMPTS_EXCEEDED      = 'MAX_NUMBER_OF_PAYMENT_ATTEMPTS_EXCEEDED';
	public const MAX_VALUE_EXCEEDED                           = 'MAX_VALUE_EXCEEDED';
	public const MISSING_REQUIRED_PARAMETER                   = 'MISSING_REQUIRED_PARAMETER';
	public const MISSING_SHIPPING_ADDRESS                     = 'MISSING_SHIPPING_ADDRESS';
	public const MULTI_CURRENCY_ORDER                         = 'MULTI_CURRENCY_ORDER';
	public const MULTIPLE_SHIPPING_ADDRESS_NOT_SUPPORTED      = 'MULTIPLE_SHIPPING_ADDRESS_NOT_SUPPORTED';
	public const MULTIPLE_SHIPPING_OPTION_SELECTED            = 'MULTIPLE_SHIPPING_OPTION_SELECTED';
	public const INVALID_PICKUP_ADDRESS                       = 'INVALID_PICKUP_ADDRESS';
	public const NOT_AUTHORIZED                               = 'NOT_AUTHORIZED';
	public const NOT_ENABLED_FOR_CARD_PROCESSING              = 'NOT_ENABLED_FOR_CARD_PROCESSING';
	public const NOT_PATCHABLE                                = 'NOT_PATCHABLE';
	public const NOT_SUPPORTED                                = 'NOT_SUPPORTED';
	public const ORDER_ALREADY_AUTHORIZED                     = 'ORDER_ALREADY_AUTHORIZED';
	public const ORDER_ALREADY_CAPTURED                       = 'ORDER_ALREADY_CAPTURED';
	public const ORDER_ALREADY_COMPLETED                      = 'ORDER_ALREADY_COMPLETED';
	public const ORDER_CANNOT_BE_SAVED                        = 'ORDER_CANNOT_BE_SAVED';
	public const ORDER_COMPLETED_OR_VOIDED                    = 'ORDER_COMPLETED_OR_VOIDED';
	public const ORDER_EXPIRED                                = 'ORDER_EXPIRED';
	public const ORDER_NOT_APPROVED                           = 'ORDER_NOT_APPROVED';
	public const ORDER_NOT_SAVED                              = 'ORDER_NOT_SAVED';
	public const ORDER_PREVIOUSLY_VOIDED                      = 'ORDER_PREVIOUSLY_VOIDED';
	public const PARAMETER_VALUE_NOT_SUPPORTED                = 'PARAMETER_VALUE_NOT_SUPPORTED';
	public const PATCH_PATH_REQUIRED                          = 'PATCH_PATH_REQUIRED';
	public const PATCH_VALUE_REQUIRED                         = 'PATCH_VALUE_REQUIRED';
	public const PAYEE_ACCOUNT_INVALID                        = 'PAYEE_ACCOUNT_INVALID';
	public const PAYEE_ACCOUNT_LOCKED_OR_CLOSED               = 'PAYEE_ACCOUNT_LOCKED_OR_CLOSED';
	public const PAYEE_ACCOUNT_RESTRICTED                     = 'PAYEE_ACCOUNT_RESTRICTED';
	public const PAYEE_BLOCKED_TRANSACTION                    = 'PAYEE_BLOCKED_TRANSACTION';
	public const PAYER_ACCOUNT_LOCKED_OR_CLOSED               = 'PAYER_ACCOUNT_LOCKED_OR_CLOSED';
	public const PAYER_ACCOUNT_RESTRICTED                     = 'PAYER_ACCOUNT_RESTRICTED';
	public const PAYER_CANNOT_PAY                             = 'PAYER_CANNOT_PAY';
	public const PAYER_CONSENT_REQUIRED                       = 'PAYER_CONSENT_REQUIRED';
	public const PAYER_COUNTRY_NOT_SUPPORTED                  = 'PAYER_COUNTRY_NOT_SUPPORTED';
	public const PAYEE_NOT_ENABLED_FOR_CARD_PROCESSING        = 'PAYEE_NOT_ENABLED_FOR_CARD_PROCESSING';
	public const PAYMENT_INSTRUCTION_REQUIRED                 = 'PAYMENT_INSTRUCTION_REQUIRED';
	public const PERMISSION_DENIED                            = 'PERMISSION_DENIED';
	public const POSTAL_CODE_REQUIRED                         = 'POSTAL_CODE_REQUIRED';
	public const PREFERRED_SHIPPING_OPTION_AMOUNT_MISMATCH    = 'PREFERRED_SHIPPING_OPTION_AMOUNT_MISMATCH';
	public const REDIRECT_PAYER_FOR_ALTERNATE_FUNDING         = 'REDIRECT_PAYER_FOR_ALTERNATE_FUNDING';
	public const REFERENCE_ID_NOT_FOUND                       = 'REFERENCE_ID_NOT_FOUND';
	public const REFERENCE_ID_REQUIRED                        = 'REFERENCE_ID_REQUIRED';
	public const DUPLICATE_REFERENCE_ID                       = 'DUPLICATE_REFERENCE_ID';
	public const SHIPPING_ADDRESS_INVALID                     = 'SHIPPING_ADDRESS_INVALID';
	public const SHIPPING_OPTION_NOT_SELECTED                 = 'SHIPPING_OPTION_NOT_SELECTED';
	public const SHIPPING_OPTIONS_NOT_SUPPORTED               = 'SHIPPING_OPTIONS_NOT_SUPPORTED';
	public const TAX_TOTAL_MISMATCH                           = 'TAX_TOTAL_MISMATCH';
	public const TAX_TOTAL_REQUIRED                           = 'TAX_TOTAL_REQUIRED';
	public const TRANSACTION_AMOUNT_EXCEEDS_MONTHLY_MAX_LIMIT = 'TRANSACTION_AMOUNT_EXCEEDS_MONTHLY_MAX_LIMIT';
	public const TRANSACTION_BLOCKED_BY_PAYEE                 = 'TRANSACTION_BLOCKED_BY_PAYEE';
	public const TRANSACTION_LIMIT_EXCEEDED                   = 'TRANSACTION_LIMIT_EXCEEDED';
	public const TRANSACTION_RECEIVING_LIMIT_EXCEEDED         = 'TRANSACTION_RECEIVING_LIMIT_EXCEEDED';
	public const TRANSACTION_REFUSED                          = 'TRANSACTION_REFUSED';
	public const UNSUPPORTED_INTENT                           = 'UNSUPPORTED_INTENT';
	public const UNSUPPORTED_PATCH_PARAMETER_VALUE            = 'UNSUPPORTED_PATCH_PARAMETER_VALUE';
	public const UNSUPPORTED_PAYMENT_INSTRUCTION              = 'UNSUPPORTED_PAYMENT_INSTRUCTION';
	public const PAYEE_ACCOUNT_NOT_SUPPORTED                  = 'PAYEE_ACCOUNT_NOT_SUPPORTED';
	public const PAYEE_ACCOUNT_NOT_VERIFIED                   = 'PAYEE_ACCOUNT_NOT_VERIFIED';
	public const PAYEE_NOT_CONSENTED                          = 'PAYEE_NOT_CONSENTED';
}
