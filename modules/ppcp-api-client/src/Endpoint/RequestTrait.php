<?php
/**
 * The RequestTrait wraps the wp_remote_get functionality for the API client.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use WP_Error;

/**
 * Trait RequestTrait
 */
trait RequestTrait {

	/**
	 * Whether to log the detailed request/response info.
	 *
	 * @var bool
	 */
	protected $is_request_logging_enabled = true;

	/**
	 * Performs a request
	 *
	 * @param string $url The URL to request.
	 * @param array  $args The arguments by which to request.
	 *
	 * @return array|WP_Error
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
			$args['headers']['PayPal-Partner-Attribution-Id'] = PPCP_PAYPAL_BN_CODE;
		}

		$response = wp_remote_get( $url, $args );
		if ( $this->is_request_logging_enabled ) {
			$this->logger->debug( $this->request_response_string( $url, $args, $response ) );
		}
		return $response;
	}

	/**
	 * Returns request and response information as string.
	 *
	 * @param string         $url The request URL.
	 * @param array          $args The request arguments.
	 * @param array|WP_Error $response The response.
	 * @return string
	 */
	private function request_response_string( string $url, array $args, $response ): string {
		$method = $args['method'] ?? '';
		$output = $method . ' ' . $url . "\n";
		if ( isset( $args['body'] ) ) {
			if ( ! in_array(
				$url,
				array(
					trailingslashit( $this->host ) . 'v1/oauth2/token/',
					trailingslashit( $this->host ) . 'v1/oauth2/token?grant_type=client_credentials',
				),
				true
			) ) {
				$output .= 'Request Body: ' . wc_print_r( $args['body'], true ) . "\n";
			}
		}

		if ( $response instanceof WP_Error ) {
			$output .= 'Request failed. WP error message: ' . implode( "\n", $response->get_error_messages() ) . "\n";
			return $output;
		}

		if ( isset( $response['headers']->getAll()['paypal-debug-id'] ) ) {
			$output .= 'Response Debug ID: ' . $response['headers']->getAll()['paypal-debug-id'] . "\n";
		}
		if ( isset( $response['response'] ) ) {
			$output .= 'Response: ' . wc_print_r( $response['response'], true ) . "\n";

			if (
				isset( $response['body'] )
				&& isset( $response['response']['code'] )
				&& ! in_array( $response['response']['code'], array( 200, 201, 202, 204 ), true )
			) {
				$output .= 'Response Body: ' . wc_print_r( $response['body'], true ) . "\n";
			}
		}

		return $output;
	}
}
