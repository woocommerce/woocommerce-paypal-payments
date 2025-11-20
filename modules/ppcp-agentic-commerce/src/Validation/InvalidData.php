<?php
// phpcs:disable Squiz.PHP.CommentedOutCode.Found

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorType;

/**
 * When to use:
 * - Provided data is incorrect, e.g., malformed email.
 * - Unexpected data format, e.g., non-numeric price.
 */
class InvalidData extends ValidationIssue {
	protected const ISSUE_CODE = ErrorCode::DATA_ERROR;
	protected const ISSUE_TYPE = ErrorType::INVALID_DATA;
}

/*
Sample:
{
	"code": "DATA_ERROR",
	"type": "INVALID_DATA",
	"message": "Shipping address could not be validated",
	"user_message": "We couldn't verify this shipping address. Please check and correct any errors.",
	"field": "shipping_address",
	"context": {
		"specific_issue": "INVALID_SHIPPING_ADDRESS",
		"suggested_address": {
			"address_line_1": "123 Main St",
			"admin_area_2": "San Jose",
			"admin_area_1": "CA",
			"postal_code": "95131-1234",
			"country_code": "US"
		}
	},
	"resolution_options": [
		{
			"action": "UPDATE_ADDRESS",
			"label": "Use suggested address",
			"metadata": {"priority": "high", "auto_applicable": true}
		},
		{
			"action": "UPDATE_ADDRESS",
			"label": "Edit address manually",
			"metadata": {"priority": "medium"}
		}
	]
}
*/
