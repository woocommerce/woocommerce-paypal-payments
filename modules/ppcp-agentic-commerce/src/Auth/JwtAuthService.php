<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use Exception;
use stdClass;
use WP_Error;
use Firebase\JWT\JWT;

class JwtAuthService {

	private PayPalJwkProvider $jwk_provider;

	public function __construct( PayPalJwkProvider $jwk_provider ) {
		$this->jwk_provider = $jwk_provider;
	}

	/**
	 * Parses and validates JWT token.
	 *
	 * @param string|null $token Bearer token from Authorization header.
	 * @return stdClass|WP_Error Decoded token or validation error.
	 */
	public function get_token( ?string $token ) {
		$string_token = trim( $token ?? '' );

		if ( $string_token === '' ) {
			return new WP_Error( 'missing_token', 'Please provide a valid token', array( 'status' => 401 ) );
		}

		if ( 0 !== stripos( $string_token, 'Bearer' ) ) {
			return new WP_Error( 'invalid_jwt', 'Please provide a valid token', array( 'status' => 401 ) );
		}

		$jwt = trim( (string) substr( $string_token, 6 ) );
		if ( empty( $jwt ) ) {
			return new WP_Error( 'missing_token', 'Bearer prefix without token found', array( 'status' => 401 ) );
		}

		$keys = $this->jwk_provider->keys();
		if ( ! $keys ) {
			return new WP_Error( 'key_unavailable', 'Could not retrieve public JWT key', array( 'status' => 503 ) );
		}

		try {
			return JWT::decode( $jwt, $keys );
		} catch ( Exception $exception ) {
			return new WP_Error( 'invalid_jwt', $exception->getMessage(), array( 'status' => 401 ) );
		}
	}
}
