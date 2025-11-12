<?php
/**
 * 404 Not Found HTTP error.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http;

use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\HttpError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\HttpErrorName;

/**
 * Use when cart ID doesn't exist in merchant's system.
 */
class NotFoundError extends HttpError {
	protected const ERROR_NAME  = HttpErrorName::CART_NOT_FOUND;
	protected const STATUS_CODE = 404;

	/**
	 * Create not found error with auto-generated debug ID.
	 */
	public function __construct( string $message, ?array $details = null, ?string $debug_id = null ) {
		parent::__construct( $message, $details, $debug_id ?? $this->generate_debug_id() );
	}
}
