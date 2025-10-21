<?php
/**
 * Tests for JWT authentication service.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;

class JwtAuthServiceTest extends TestCase {

	/**
	 * GIVEN no Authorization header exists
	 * WHEN validate_request is called
	 * THEN should return WP_Error with 'missing_token' code and 401 status
	 */
	public function test_validate_request_returns_error_when_no_authorization_header(): void {
		$service = new JwtAuthService();

		$result = $service->validate_request( null );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_token', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}
}
