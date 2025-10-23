<?php
// phpcs:disable Squiz.PHP.CommentedOutCode.Found

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Errors;

/**
 * When to use:
 * - Invalid cart ID provided
 */
class CartNotFoundError extends AgenticError {
	protected const ERROR_NAME  = 'CART_NOT_FOUND';
	protected const STATUS_CODE = 404;
}

/*
Sample:
{
	"name": "CART_NOT_FOUND",
	"message": "Cart with ID 'CART-MISSING-123' does not exist",
	"debug_id": "ERROR-404-12345",
	"details": [
		{
			"field": "cartId",
			"issue": "NOT_FOUND",
			"description": "Cart with ID 'CART-MISSING-123' does not exist for merchant 'MERCHANT_789'. Verify cart ID or create a new cart."
		}
	]
}
*/
