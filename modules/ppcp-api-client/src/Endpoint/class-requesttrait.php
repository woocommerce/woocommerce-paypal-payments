<?php
/**
 * The RequestTrait wraps the wp_remote_get functionality for the API client.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

/**
 * Trait RequestTrait
 */
trait RequestTrait {

	/**
	 * Performs a request
	 *
	 * @param string $url The URL to request.
	 * @param array  $args The arguments by which to request.
	 *
	 * @return array|\WP_Error
	 */
	private function request( string $url, array $args ) {

		/**
		 * This filter can be used to alter the request args.
		 * For example, during testing, the PayPal-Mock-Response header could be
		 * added here.
		 */
		$args = apply_filters( 'ppcp_request_args', $args, $url );
		if ( ! isset( $args['headers']['PayPal-Partner-Attribution-Id'] ) ) {
			$args['headers']['PayPal-Partner-Attribution-Id'] = 'Woo_PPCP';
		}

		return wp_remote_get( $url, $args );
	}
}
