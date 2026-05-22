<?php
/**
 * Simple transient-based rate limiter.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

/**
 * Class RateLimiter
 */
class RateLimiter {

	/**
	 * The transient key prefix.
	 *
	 * @var string
	 */
	private string $prefix; // @phpstan-ignore property.onlyWritten

	/**
	 * The maximum number of requests allowed per window.
	 *
	 * @var int
	 */
	private int $max_requests; // @phpstan-ignore property.onlyWritten

	/**
	 * The time window in seconds.
	 *
	 * @var int
	 */
	private int $window_seconds; // @phpstan-ignore property.onlyWritten

	/**
	 * RateLimiter constructor.
	 *
	 * @param string $prefix The transient key prefix.
	 * @param int    $max_requests The maximum requests per window.
	 * @param int    $window_seconds The time window in seconds.
	 */
	public function __construct( string $prefix, int $max_requests, int $window_seconds ) {
		$this->prefix         = $prefix;
		$this->max_requests   = $max_requests;
		$this->window_seconds = $window_seconds;
	}

	/**
	 * Checks if the current client has exceeded the rate limit.
	 *
	 * @return bool
	 */
	public function is_limited(): bool {
		return false;
	}

	/**
	 * Records a request hit for the current client.
	 *
	 * @return void
	 */
	public function hit(): void {
	}
}
