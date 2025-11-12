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

	/**
	 * Create internal server error with auto-generated debug ID.
	 */
	public function __construct( string $message, ?array $details = null, ?string $debug_id = null ) {
		parent::__construct( $message, $details, $debug_id ?? $this->generate_debug_id() );
	}
}
