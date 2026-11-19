<?php
/**
 * Thrown when a token cannot be requested yet, without PayPal having been asked.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Exception
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Exception;

/**
 * The plugin is not connected, or token requests are paused after a failure.
 * Both are expected states rather than errors, and both say how long to wait.
 */
class TokenUnavailableException extends RuntimeException {

	private int $retry_after;

	/**
	 * @param string $message     The reason.
	 * @param int    $retry_after Seconds until a new attempt makes sense.
	 */
	public function __construct( string $message, int $retry_after ) {
		parent::__construct( $message );

		$this->retry_after = max( 0, $retry_after );
	}

	/**
	 * Seconds until a new attempt makes sense.
	 */
	public function retry_after(): int {
		return $this->retry_after;
	}
}
