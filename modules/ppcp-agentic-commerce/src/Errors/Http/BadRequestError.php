<?php
/**
 * 400 Bad Request HTTP error.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\HttpErrorName;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\HttpError;

/**
 * Use for invalid request format, malformed JSON, missing required fields.
 */
class BadRequestError extends HttpError {
	protected const ERROR_NAME  = HttpErrorName::INVALID_REQUEST;
	protected const STATUS_CODE = 400;

	/**
	 * Create bad request error with auto-generated debug ID.
	 */
	public function __construct( string $message, ?array $details = null, ?string $debug_id = null ) {
		parent::__construct( $message, $details, $debug_id ?? $this->generate_debug_id() );
	}
}
