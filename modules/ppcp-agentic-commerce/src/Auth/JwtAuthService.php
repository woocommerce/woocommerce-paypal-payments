<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use WP_Error;

class JwtAuthService {

	public function validate_request() {
		return new WP_Error( 'missing_token', '', array('status' => 401 ) );
	}
}
