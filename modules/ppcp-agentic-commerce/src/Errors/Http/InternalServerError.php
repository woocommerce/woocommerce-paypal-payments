<?php
/**
 * 500 Internal Server Error HTTP error.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http;

use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\HttpError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\HttpErrorName;

/**
 * Use for system errors, database failures, third-party service issues.
 */
class InternalServerError extends HttpError {
	protected const ERROR_NAME  = HttpErrorName::INTERNAL_SERVER_ERROR;
	protected const STATUS_CODE = 500;
}
