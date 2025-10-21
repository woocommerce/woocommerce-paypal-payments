<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use WP_Error;

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

		// temp response.
		return new WP_Error( 'valid_jwt', '', array( 'status' => 200 ) );
	}
}
