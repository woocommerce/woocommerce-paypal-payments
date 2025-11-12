<?php
/**
 * Base class for HTTP errors (400, 404, 422, 500).
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Errors
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Errors;

/**
 * HTTP errors represent technical failures and invalid requests.
 * Use when request format is incorrect, authentication fails, or system errors occur.
 */
abstract class HttpError extends AgenticError {
	private ?string $debug_id;

	/**
	 * Create HTTP error with optional debug ID.
	 *
	 * @param string      $message  Descriptive error message.
	 * @param array|null  $details  Optional error details.
	 * @param string|null $debug_id Optional debug identifier for support.
	 */
	public function __construct( string $message, ?array $details = null, ?string $debug_id = null ) {
		parent::__construct( $message, $details );
		$this->debug_id = $debug_id;
	}

	/**
	 * Convert to array with debug_id when present.
	 */
	public function to_array(): array {
		$data = parent::to_array();

		if ( $this->debug_id ) {
			$data['debug_id'] = $this->debug_id;
		}

		return $data;
	}

	/**
	 * Generate a debug ID if not provided.
	 */
	protected function generate_debug_id(): string {
		return 'ERROR-' . $this->get_status_code() . '-' . strtoupper( substr( md5( uniqid() ), 0, 8 ) );
	}
}
