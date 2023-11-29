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
	 * @param string $nonce The nonce.
	 *
	 * @return array
	 * @throws RuntimeException When nonce validation fails.
	 */
	public function read_request( string $nonce ): array {
		$stream = file_get_contents( 'php://input' );
		$json   = json_decode( $stream, true );
		$this->enqueue_nonce_fix();
		if (
			! isset( $json['nonce'] )
			|| ! wp_verify_nonce( $json['nonce'], $nonce )
		) {
			remove_filter( 'nonce_user_logged_out', array( $this, 'nonce_fix' ), 100 );
			throw new RuntimeException(
				__( 'Could not validate nonce.', 'woocommerce-paypal-payments' )
			);
		}
		$this->dequeue_nonce_fix();

		if ( isset( $json['form_encoded'] ) ) {
			$json['form'] = array();
			parse_str( $json['form_encoded'], $json['form'] );
		}

		$sanitized = $this->sanitize( $json );
		return $sanitized;
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
			if ( $raw_key === 'form_encoded' ) {
				$data[ $raw_key ] = $raw_value;
				continue;
			}
			if ( ! is_array( $raw_value ) ) {
				// Not sure if it is a good idea to sanitize everything at this level,
				// but should be fine for now since we do not send any HTML or multi-line texts via ajax.
				$data[ sanitize_text_field( (string) $raw_key ) ] = sanitize_text_field( (string) $raw_value );
				continue;
			}
			$data[ sanitize_text_field( (string) $raw_key ) ] = $this->sanitize( $raw_value );
		}
		return $data;
	}
}
