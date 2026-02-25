<?php

// phpcs:disable Squiz.PHP.CommentedOutCode.Found
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorType;
/**
 * When to use:
 * - Required information missing, e.g., missing shipping address.
 */
class MissingField extends \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
{
    protected const ISSUE_CODE = ErrorCode::DATA_ERROR;
    protected const ISSUE_TYPE = ErrorType::MISSING_FIELD;
}
