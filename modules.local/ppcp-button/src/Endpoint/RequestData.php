<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;

class RequestData {


	public function enqueueNonceFix() {
		add_filter( 'nonce_user_logged_out', array( $this, 'nonceFix' ), 100 );
	}

	public function dequeueNonceFix() {
		remove_filter( 'nonce_user_logged_out', array( $this, 'nonceFix' ), 100 );
	}

	public function readRequest( string $nonce ): array {
		$stream = file_get_contents( 'php://input' );
		$json   = json_decode( $stream, true );
		$this->enqueueNonceFix();
		if (
			! isset( $json['nonce'] )
			|| ! wp_verify_nonce( $json['nonce'], $nonce )
		) {
			remove_filter( 'nonce_user_logged_out', array( $this, 'nonceFix' ), 100 );
			throw new RuntimeException(
				__( 'Could not validate nonce.', 'woocommerce-paypal-commerce-gateway' )
			);
		}
		$this->dequeueNonceFix();

		$sanitized = $this->sanitize( $json );
		return $sanitized;
	}

	/**
	 * woocommerce will give you a customer object on your 2nd request. the first page
	 * load will not yet have this customer object, but the ajax request will. Therefore
	 * the nonce validation will fail. this fixes this problem:
	 *
	 * @wp-hook nonce_user_logged_out
	 * @see https://github.com/woocommerce/woocommerce/blob/69e3835041113bee80379c1037e97e26815a699b/includes/class-wc-session-handler.php#L288-L296
	 * @return int
	 */
	public function nonceFix(): int {
		return 0;
	}

	private function sanitize( array $assocArray ): array {
		$data = array();
		foreach ( (array) $assocArray as $rawKey => $rawValue ) {
			if ( ! is_array( $rawValue ) ) {
				$data[ sanitize_text_field( urldecode( (string) $rawKey ) ) ] = sanitize_text_field( urldecode( (string) $rawValue ) );
				continue;
			}
			$data[ sanitize_text_field( urldecode( (string) $rawKey ) ) ] = $this->sanitize( $rawValue );
		}
		return $data;
	}
}
