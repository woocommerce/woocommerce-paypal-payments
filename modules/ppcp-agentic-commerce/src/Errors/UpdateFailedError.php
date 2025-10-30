<?php
// phpcs:disable Squiz.PHP.CommentedOutCode.Found

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Errors;

/**
 * When to use:
 * - Failed to update cart in session
 * - Session save operation failed
 */
class UpdateFailedError extends AgenticError {
	protected const ERROR_NAME  = 'UPDATE_FAILED';
	protected const STATUS_CODE = 500;
}

/*
Sample:
{
	"name": "UPDATE_FAILED",
	"message": "Failed to update cart",
	"debug_id": "ERROR-500-12347",
	"details": [
		{
			"issue": "CART_UPDATE_FAILED",
			"description": "Unable to persist cart changes to session. Try again or create a new cart."
		}
	]
}
*/
