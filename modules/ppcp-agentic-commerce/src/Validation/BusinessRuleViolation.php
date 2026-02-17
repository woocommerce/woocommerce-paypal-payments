<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorType;
/**
 * A generic business rule issue, intended as a base class for
 * more specific issues or third party code.
 */
class BusinessRuleViolation extends \WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue
{
    protected const ISSUE_TYPE = ErrorType::BUSINESS_RULE;
    protected const ISSUE_CODE = ErrorCode::BUSINESS_RULE_ERROR;
}
