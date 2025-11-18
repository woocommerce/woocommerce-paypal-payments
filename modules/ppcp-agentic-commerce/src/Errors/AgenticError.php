<?php
/**
 * Base class for all agentic commerce REST errors.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Errors
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Errors;

use RuntimeException;
use WP_Error;

abstract class AgenticError {
	/**
	 * The error name is defined by the PayPal API, usually in upper-case, e.g. "INVALID_REQUEST".
	 * Child classes must define this constant.
	 */
	protected const ERROR_NAME = '';

	/**
	 * The HTTP status code for the error, usually 400 or 500.
	 * Child classes must define this constant.
	 */
	protected const STATUS_CODE = 0;

	private string $message;
	private ?array $details;

	/**
	 * Defines the error contents.
	 *
	 * @param string     $message Descriptive text of the error.
	 * @param array|null $details Optional. Additional details about the error.
	 * @throws RuntimeException When the error specs are incomplete.
	 */
	public function __construct( string $message, ?array $details = null ) {
		if ( empty( static::ERROR_NAME ) ) {
			throw new RuntimeException( 'Child classes must override ERROR_NAME constant' );
		}
		if ( ! is_numeric( static::STATUS_CODE ) || static::STATUS_CODE < 400 ) {
			throw new RuntimeException( 'Child classes must define a valid STATUS_CODE constant' );
		}
		if ( empty( $message ) ) {
			throw new RuntimeException( 'Error message cannot be empty' );
		}

		$this->message = $message;
		$this->details = $details;
	}

	public function get_status_code(): int {
		return static::STATUS_CODE;
	}

	public function to_array(): array {
		$data = array(
			'name'    => static::ERROR_NAME,
			'message' => $this->message,
		);

		if ( $this->details ) {
			$data['details'] = $this->details;
		}

		return $data;
	}

	/**
	 * Create an instance from WP_Error using late static binding.
	 *
	 * @param WP_Error $wp_error The WordPress error to convert.
	 * @return static Instance of the called class.
	 */
	public static function from_wp_error( WP_Error $wp_error ): AgenticError {
		$message = $wp_error->get_error_message();
		$details = static::extract_wp_error_details( $wp_error );

		/**
		 * @psalm-suppress MissingThrowsDocblock, UnsafeInstantiation
		 *  Parent constructor throws only on developer errors, like missing ERROR_NAME, or an
		 *  invalid STATUS_CODE. These are implementation issues that should fail fast, not
		 *  runtime errors requiring handling.
		 */
		return new static( $message, $details );
	}

	/**
	 * Extract details from WP_Error.
	 *
	 * @param WP_Error $wp_error The WordPress error.
	 * @return array Error details.
	 */
	private static function extract_wp_error_details( WP_Error $wp_error ): array {
		$details = array(
			'wp_error_codes'    => $wp_error->get_error_codes(),
			'wp_error_messages' => array(),
			'wp_error_data'     => array(),
		);

		foreach ( $wp_error->get_error_codes() as $code ) {
			$details['wp_error_messages'][ $code ] = $wp_error->get_error_messages( $code );

			$error_data = $wp_error->get_error_data( $code );
			if ( ! empty( $error_data ) ) {
				$details['wp_error_data'][ $code ] = $error_data;
			}
		}

		return $details;
	}
}
