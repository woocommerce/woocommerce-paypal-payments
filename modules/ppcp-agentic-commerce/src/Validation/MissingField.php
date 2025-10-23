<?php
// phpcs:disable Squiz.PHP.CommentedOutCode.Found

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Validation;

/**
 * When to use:
 * - Required information missing, e.g., missing shipping address.
 */
class MissingField extends ValidationIssue {
	protected const ISSUE_CODE = 'DATA_ERROR';
	protected const ISSUE_TYPE = 'MISSING_FIELD';
}

/*
Sample:
{
	"code": "DATA_ERROR",
	"type": "MISSING_FIELD",
	"message": "Required field missing",
	"user_message": "Please provide a shipping address to calculate delivery options.",
	"field": "shipping_address",
	"context": {
		"specific_issue": "MISSING_REQUIRED_FIELD",
		"missing_fields": ["shipping_address.postal_code"],
		"field_requirements": {
			"postal_code": {
				"format": "^[0-9]{5}(-[0-9]{4})?$",
				"example": "95131"
			}
		}
	},
	"resolution_options": [
		{
			"action": "PROVIDE_MISSING_FIELD",
			"label": "Add shipping address",
			"metadata": {"priority": "HIGH"}
		}
	]
}
*/
