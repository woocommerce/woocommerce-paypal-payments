<?php
/**
 * Helper to read request data for the endpoints.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;

/**
 * Class RequestData
 */
class RequestData {

	/**
	 * Enqueues the nonce fix hook.
	 */
	public function enqueue_nonce_fix() {
		add_filter( 'nonce_user_logged_out', array( $this, 'nonce_fix' ), 100 );
	}

	/**
	 * Dequeues the nonce fix hook.
	 */
	public function dequeue_nonce_fix() {
		remove_filter( 'nonce_user_logged_out', array( $this, 'nonce_fix' ), 100 );
	}

	/**
	 * Reads the current request.
	 *
	 * @param string $action The nonce action.
	 *
	 * @return array
	 * @throws RuntimeException When read request fails.
	 */
	public function read_request( string $action ): array {
		$stream = $this->get_stream();
		$json   = $this->get_decoded_json( $stream );
		$this->ensure_nonce( $json['nonce'], $action );

		return $this->sanitize( $json );
	}

	/**
	 * WooCommerce will give you a customer object on your 2nd request. the first page
	 * load will not yet have this customer object, but the ajax request will. Therefore
	 * the nonce validation will fail. this fixes this problem:
	 *
	 * @wp-hook nonce_user_logged_out
	 * @see https://github.com/woocommerce/woocommerce/blob/69e3835041113bee80379c1037e97e26815a699b/includes/class-wc-session-handler.php#L288-L296
	 * @return int
	 */
	public function nonce_fix(): int {
		return 0;
	}

	/**
	 * Sanitizes the data.
	 *
	 * @param array $assoc_array The data array.
	 *
	 * @return array
	 */
	private function sanitize( array $assoc_array ): array {
		$data = array();
		foreach ( (array) $assoc_array as $raw_key => $raw_value ) {
			if ( ! is_array( $raw_value ) ) {
				/**
				 * The 'form' key is preserved for url encoded data and needs different
				 * sanitization.
				 */
				if ( 'form' !== $raw_key ) {
					$data[ sanitize_text_field( (string) $raw_key ) ] = sanitize_text_field( (string) $raw_value );
				} else {
					$data[ sanitize_text_field( (string) $raw_key ) ] = sanitize_text_field( urldecode( (string) $raw_value ) );
				}
				continue;
			}
			$data[ sanitize_text_field( (string) $raw_key ) ] = $this->sanitize( $raw_value );
		}
		return $data;
	}

	/**
	 * Gets the stream.
	 *
	 * @return string The stream.
	 * @throws RuntimeException When getting stream fails.
	 */
	private function get_stream(): string {
		$stream = file_get_contents( 'php://input' );
		if ( ! $stream ) {
			remove_filter( 'nonce_user_logged_out', array( $this, 'nonce_fix' ), 100 );
			throw new RuntimeException(
				__( 'Could not get stream.', 'woocommerce-paypal-payments' )
			);
		}
		return $stream;
	}

	/**
	 * Decodes json stream.
	 *
	 * @param string $stream The stream.
	 * @return mixed JSON decoded.
	 * @throws RuntimeException When getting JSON fails.
	 */
	private function get_decoded_json( string $stream ) {
		$json = json_decode( $stream, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $json['nonce'] ) ) {
			remove_filter( 'nonce_user_logged_out', array( $this, 'nonce_fix' ), 100 );
			throw new RuntimeException(
				__( 'Could not decode JSON.', 'woocommerce-paypal-payments' )
			);
		}
		return $json;
	}

	/**
	 * Ensure nonce is valid.
	 *
	 * @param string $nonce The nonce.
	 * @param string $action The action.
	 * @throws RuntimeException When nonce validation fails.
	 */
	private function ensure_nonce( string $nonce, string $action ): void {
		$this->enqueue_nonce_fix();
		if ( ! wp_verify_nonce( $nonce, $action ) ) {
			remove_filter( 'nonce_user_logged_out', array( $this, 'nonce_fix' ), 100 );
			throw new RuntimeException(
				__( 'Could not validate nonce.', 'woocommerce-paypal-payments' )
			);
		}
		$this->dequeue_nonce_fix();
	}
}
