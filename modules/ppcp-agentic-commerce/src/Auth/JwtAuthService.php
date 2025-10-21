<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use WP_Error;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;

class JwtAuthService {

	public function validate_request( ?string $token ): WP_Error {
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

		try {
			$decoded = JWT::decode( $jwt, new Key( $this->public_key_string(), 'HS256' ) );
		} catch ( SignatureInvalidException $exception ) {
			return new WP_Error( 'invalid_jwt', '', array( 'status' => 401 ) );
		}

		// temp response.
		return new WP_Error( 'valid_jwt', '', array( 'status' => 200 ) );
	}

	private function public_key_string(): string {
		return <<<'EOF'
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvv7Pi1nWWrJj4n5+6gX9
B7BQpctaPEg9VdVK1kzc9xBNwZobeWEgEmiUGtkrn8S5R6Q4NmB4hnb8F5jeCX5O
kyA49mgzw4wNXUPGTGMY5Eoxt9zu1Heaivkljh4+wN6d01oIFkHT6E7VjEJOG2RA
49t7fgQ1phJIUK39B0RAXIG2pYicbujeiiJ12iQipMjY/TVD0KZgUc2Vj2apk7Dv
1YBqFG+HlSG5hWu880IzGQE9Pds5qekIawJJyed08otq29hDHlFd28B0fFhdzcu8
cN83NxddXBlh77b8+a7gaWC5/Iw45THRpIsiG41uX0r0INEDcnR3qCUkz6m9LOVW
kQIDAQAB
EOF;
	}
}
