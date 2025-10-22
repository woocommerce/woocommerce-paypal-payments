<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use WP_Error;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;

class JwtAuthService {

	private PayPalJwkProvider $jwk_provider;

	public function __construct( PayPalJwkProvider $jwk_provider ) {
		$this->jwk_provider = $jwk_provider;
	}

	/**
	 * @param string|null $token
	 * @return \stdClass|WP_Error
	 */
	public function validate_request( ?string $token ) {
		$string_token = trim( $token ?? '' );

		if ( $string_token === '' ) {
			return new WP_Error( 'missing_token', '', array( 'status' => 401 ) );
		}

		if ( 0 !== stripos( $string_token, 'Bearer' ) ) {
			return new WP_Error( 'invalid_jwt', '', array( 'status' => 401 ) );
		}

		$jwt = trim( (string) substr( $string_token, 6 ) );
		if ( empty( $jwt ) ) {
			return new WP_Error( 'missing_token', '', array( 'status' => 401 ) );
		}

		$keys = $this->jwk_provider->keys();
		if ( ! $keys ) {
			return new WP_Error( 'key_unavailable', '', array( 'status' => 503 ) );
		}

		try {
			return JWT::decode( $jwt, $keys );
		} catch ( SignatureInvalidException $exception ) {
			return new WP_Error( 'invalid_jwt', '', array( 'status' => 401 ) );
		} catch ( ExpiredException $exception ) {
			return new WP_Error( 'invalid_jwt', '', array( 'status' => 401 ) );
		} catch ( BeforeValidException $exception ) {
			return new WP_Error( 'invalid_jwt', '', array( 'status' => 401 ) );
		}
	}
}
