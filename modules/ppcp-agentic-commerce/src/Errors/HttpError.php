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
 * Use when the request format is incorrect, authentication fails, or system errors occur.
 */
abstract class HttpError extends AgenticError {
	private ?string $debug_id;

	/**
	 * Create an HTTP error with optional debug ID.
	 *
	 * @param string      $message  Descriptive error message.
	 * @param array|null  $details  Optional error details.
	 * @param string|null $debug_id Optional debug identifier for support.
	 */
	public function __construct( string $message, ?array $details = null, ?string $debug_id = null ) {
		/**
		 * @psalm-suppress MissingThrowsDocblock, UnsafeInstantiation
		 *  Parent constructor throws only on developer errors, like missing ERROR_NAME, or an
		 *  invalid STATUS_CODE. These are implementation issues that should fail fast, not
		 *  runtime errors requiring handling.
		 */
		parent::__construct( $message, $details );

		$this->debug_id = $debug_id ?? $this->generate_debug_id();
	}

	/**
	 * Convert to an array with debug_id when present.
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
		return sprintf(
			'ERROR-%s-%s',
			$this->get_status_code(),
			strtoupper( (string) substr( md5( uniqid( '', true ) ), 0, 8 ) )
		);
	}
}
