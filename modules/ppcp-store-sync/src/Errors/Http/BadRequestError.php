<?php
/**
 * 400 Bad Request HTTP error.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\HttpErrorName;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\AgenticError;

/**
 * Use for invalid request format, malformed JSON, missing required fields.
 */
class BadRequestError extends AgenticError {
	protected const ERROR_NAME  = HttpErrorName::INVALID_REQUEST;
	protected const STATUS_CODE = 400;
}
