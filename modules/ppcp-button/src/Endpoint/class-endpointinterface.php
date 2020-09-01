<?php
/**
 * The Endpoint interface.
 *
 * @package Inpsyde\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

/**
 * Interface EndpointInterface
 */
interface EndpointInterface {

	/**
	 * Returns the nonce for an endpoint.
	 *
	 * @return string
	 */
	public static function nonce(): string;

	/**
	 * Handles the request for an endpoint.
	 *
	 * @return bool
	 */
	public function handle_request(): bool;
}
