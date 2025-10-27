<?php
// phpcs:disable Squiz.PHP.CommentedOutCode.Found

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Errors;

/**
 * When to use:
 * - Empty request body
 * - Invalid JSON body
 * - Malformed request data
 */
class InvalidRequestError extends AgenticError {
	protected const ERROR_NAME  = 'INVALID_REQUEST';
	protected const STATUS_CODE = 400;
}

/*
Sample:
{
	"name": "INVALID_REQUEST",
	"message": "Request body contains invalid JSON. Error: Syntax error",
	"debug_id": "ERROR-400-12348",
	"details": [
		{
			"issue": "MALFORMED_JSON",
			"description": "Request body must contain valid JSON"
		}
	]
}
*/
