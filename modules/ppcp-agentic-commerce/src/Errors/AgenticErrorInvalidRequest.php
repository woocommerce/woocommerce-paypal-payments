<?php
// phpcs:disable Squiz.PHP.CommentedOutCode.Found

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Errors;

/**
 * When to use:
 * - Empty request body
 * - Malformed request
 * - Missing mandatory fields
 */
class AgenticErrorInvalidRequest extends AgenticError {
	protected const ERROR_NAME  = 'INVALID_REQUEST';
	protected const STATUS_CODE = 400;
}

/*
Sample:
{
	"name": "INVALID_REQUEST",
	"message": "Required field 'items' is missing",
	"details": [
		{
			"field": "items",
			"issue": "MISSING_REQUIRED_FIELD",
			"description": "The items field is required and cannot be empty."
		}
	]
}
*/
