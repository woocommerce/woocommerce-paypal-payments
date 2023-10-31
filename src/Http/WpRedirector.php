<?php
/**
 * HTTP redirection.
 *
 * @package WooCommerce\PayPalCommerce\Api
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Http;

/**
 * Wrapper for HTTP redirection via wp_safe_redirect.
 */
class WpRedirector implements RedirectorInterface {
	/**
	 * Starts HTTP redirection and shutdowns.
	 *
	 * @param string $location The URL to redirect to.
	 */
	public function redirect( string $location ): void {
		wp_safe_redirect( $location, 302 );
		exit;
	}
}
