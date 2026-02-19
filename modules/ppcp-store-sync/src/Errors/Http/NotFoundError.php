<?php
/**
 * 404 Not Found HTTP error.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http;

use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\AgenticError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\HttpErrorName;

/**
 * Use when cart ID doesn't exist in merchant's system.
 */
class NotFoundError extends AgenticError {
	protected const ERROR_NAME  = HttpErrorName::CART_NOT_FOUND;
	protected const STATUS_CODE = 404;
}
