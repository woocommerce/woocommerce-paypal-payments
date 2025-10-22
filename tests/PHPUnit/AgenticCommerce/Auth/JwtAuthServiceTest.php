<?php
/**
 * Tests for JWT authentication service.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;
use Mockery;
use Firebase\JWT\Key;
use Firebase\JWT\JWT;

class JwtAuthServiceTest extends TestCase {

	/**
	 * GIVEN no Authorization header exists
	 * WHEN validate_request is called
	 * THEN should return WP_Error with 'missing_token' code and 401 status
	 */
	public function test_validate_request_returns_error_when_no_authorization_header(): void {
		$provider = Mockery::mock( PayPalJwkProvider::class );
		$service  = new JwtAuthService( $provider );

		$result = $service->validate_request( null );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_token', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	/**
	 * GIVEN empty or whitespace-only authorization header
	 * WHEN validate_request is called
	 * THEN should return WP_Error with 'missing_token' code and 401 status
	 *
	 * @dataProvider emptyTokenProvider
	 */
	public function test_validate_request_handles_empty_tokens( string $token ): void {
		$provider = Mockery::mock( PayPalJwkProvider::class );
		$service  = new JwtAuthService( $provider );

		$result = $service->validate_request( $token );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'missing_token', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function emptyTokenProvider(): array {
		return [
			'empty string'         => [ '' ],
			'only whitespace'      => [ '   ' ],
			'bearer with no token' => [ 'Bearer ' ],
			'bearer whitespace'    => [ 'Bearer   ' ],
		];
	}

	/**
	 * GIVEN malformed authorization header (not Bearer format)
	 * WHEN validate_request is called
	 * THEN should return WP_Error with 'invalid_jwt' code and 401 status
	 */
	public function test_validate_request_rejects_malformed_format(): void {
		$provider = Mockery::mock( PayPalJwkProvider::class );
		$service  = new JwtAuthService( $provider );

		$result = $service->validate_request( 'NotBearerFormat' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_jwt', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	/**
	 * GIVEN valid Bearer format but invalid JWT token
	 * WHEN validate_request is called
	 * THEN should return WP_Error with 'invalid_jwt' code and 401 status
	 */
	public function test_validate_request_rejects_invalid_jwt(): void {
		$key = new Key( 'test-key-material', 'HS256' );

		$provider = Mockery::mock( PayPalJwkProvider::class );
		$provider->shouldReceive( 'keys' )
			->once()
			->andReturn( $key );

		$service = new JwtAuthService( $provider );

		// Invalid JWT: malformed, wrong signature, or can't be decoded
		$invalid_jwt = 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.invalid_signature';

		$result = $service->validate_request( $invalid_jwt );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_jwt', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	/**
	 * GIVEN provider returns null (key unavailable)
	 * WHEN validate_request is called with valid Bearer token
	 * THEN should return WP_Error with 'key_unavailable' code and 503 status
	 */
	public function test_validate_request_handles_unavailable_key(): void {
		$provider = Mockery::mock( PayPalJwkProvider::class );
		$provider->shouldReceive( 'keys' )
			->once()
			->andReturn( null );

		$service = new JwtAuthService( $provider );

		$result = $service->validate_request( 'Bearer some.valid.token' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'key_unavailable', $result->get_error_code() );
		$this->assertSame( 503, $result->get_error_data()['status'] );
	}

	/**
	 * GIVEN valid Bearer token with correct signature
	 * WHEN validate_request is called
	 * THEN should return decoded stdClass payload
	 */
	public function test_validate_request_returns_decoded_payload_for_valid_jwt(): void {
		$expected_payload = (object) array(
			'sub'  => '1234567890',
			'name' => 'John Doe',
			'iat'  => 1516239022,
		);

		$key = new Key( 'test-secret-key', 'HS256' );

		$provider = Mockery::mock( PayPalJwkProvider::class );
		$provider->shouldReceive( 'keys' )
			->once()
			->andReturn( $key );

		$service = new JwtAuthService( $provider );

		// Generate a real valid JWT using the same secret
		$valid_jwt = JWT::encode( (array) $expected_payload, 'test-secret-key', 'HS256' );
		$token     = 'Bearer ' . $valid_jwt;

		$result = $service->validate_request( $token );

		$this->assertInstanceOf( \stdClass::class, $result );
		$this->assertEquals( $expected_payload, $result );
	}

	/**
	 * GIVEN expired JWT token
	 * WHEN validate_request is called
	 * THEN should return WP_Error with 'invalid_jwt' code
	 */
	public function test_validate_request_rejects_expired_jwt(): void {
		$expired_payload = array(
			'sub' => '1234567890',
			'exp' => time() - 3600, // Expired 1 hour ago
		);

		$key = new Key( 'test-secret-key', 'HS256' );

		$provider = Mockery::mock( PayPalJwkProvider::class );
		$provider->shouldReceive( 'keys' )
			->once()
			->andReturn( $key );

		$service = new JwtAuthService( $provider );

		$expired_jwt = JWT::encode( $expired_payload, 'test-secret-key', 'HS256' );
		$token       = 'Bearer ' . $expired_jwt;

		$result = $service->validate_request( $token );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_jwt', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	/**
	 * GIVEN the JWT token is not valid yet
	 * WHEN validate_request is called
	 * THEN should return WP_Error with 'invalid_jwt' code
	 */
	public function test_validate_request_rejects_not_yet_valid_jwt(): void {
		$expired_payload = array(
			'sub' => '1234567890',
			'nbf' => time() + 3600, // Valid in hour, but not right now
		);

		$key = new Key( 'test-secret-key', 'HS256' );

		$provider = Mockery::mock( PayPalJwkProvider::class );
		$provider->shouldReceive( 'keys' )
			->once()
			->andReturn( $key );

		$service = new JwtAuthService( $provider );

		$expired_jwt = JWT::encode( $expired_payload, 'test-secret-key', 'HS256' );
		$token       = 'Bearer ' . $expired_jwt;

		$result = $service->validate_request( $token );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_jwt', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}
}
