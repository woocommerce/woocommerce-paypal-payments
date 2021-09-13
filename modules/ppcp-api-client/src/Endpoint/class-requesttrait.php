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

		$args['timeout'] = 30;

		/**
		 * This filter can be used to alter the request args.
		 * For example, during testing, the PayPal-Mock-Response header could be
		 * added here.
		 */
		$args = apply_filters( 'ppcp_request_args', $args, $url );
		if ( ! isset( $args['headers']['PayPal-Partner-Attribution-Id'] ) ) {
			$args['headers']['PayPal-Partner-Attribution-Id'] = 'Woo_PPCP';
		}

		$response = wp_remote_get( $url, $args );

		$this->logger->log( 'info', '--------------------------------------------------------------------' );
		$this->logger->log( 'info', 'URL: ' . wc_print_r( $url, true ) );
		if ( isset( $args['method'] ) ) {
			$this->logger->log( 'info', 'Method: ' . wc_print_r( $args['method'], true ) );
		}
		if ( isset( $args['headers']['body'] ) ) {
			$this->logger->log( 'info', 'Request Body: ' . wc_print_r( $args['headers']['body'], true ) );
		}
		if ( isset( $response['headers']->getAll()['paypal-debug-id'] ) ) {
			$this->logger->log( 'info', 'Response Debug ID: ' . wc_print_r( $response['headers']->getAll()['paypal-debug-id'], true ) );
		}
		if ( isset( $response['response'] ) ) {
			$this->logger->log( 'info', 'Response: ' . wc_print_r( $response['response'], true ) );
		}
		if ( isset( $response['body'] ) ) {
			$this->logger->log( 'info', 'Response Body: ' . wc_print_r( $response['body'], true ) );
		}

		return $response;
	}
}
