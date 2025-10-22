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

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\Auth\JwtAuthService
 */
class JwtAuthServiceTest extends TestCase {

	/**
	 * GIVEN invalid or missing tokens
	 * WHEN validate_request is called
	 * THEN should return WP_Error with appropriate code and 401 status
	 *
	 * @dataProvider invalidTokenProvider
	 */
	public function test_validate_request_rejects_invalid_tokens(
		?string $token,
		string $expected_error_code,
		bool $needs_key
	): void {
		$provider = Mockery::mock( PayPalJwkProvider::class );

		if ( $needs_key ) {
			$key = new Key( 'test-secret-key', 'HS256' );
			$provider->shouldReceive( 'keys' )
				->once()
				->andReturn( $key );
		} else {
			$provider->shouldReceive( 'keys' )->never();
		}

		$service = new JwtAuthService( $provider );
		$result  = $service->validate_request( $token );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( $expected_error_code, $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function invalidTokenProvider(): array {
		$invalid_jwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.invalid_signature';
		$expired_jwt = JWT::encode(
			array(
				'sub' => '1234567890',
				'exp' => time() - 3600,
			),
			'test-secret-key',
			'HS256'
		);
		$future_jwt  = JWT::encode(
			array(
				'sub' => '1234567890',
				'nbf' => time() + 3600,
			),
			'test-secret-key',
			'HS256'
		);

		return array(
			'null token'           => array(
				'token'      => null,
				'error_code' => 'missing_token',
				'needs_key'  => false,
			),
			'empty string'         => array(
				'token'      => '',
				'error_code' => 'missing_token',
				'needs_key'  => false,
			),
			'only whitespace'      => array(
				'token'      => '   ',
				'error_code' => 'missing_token',
				'needs_key'  => false,
			),
			'bearer with no token' => array(
				'token'      => 'Bearer ',
				'error_code' => 'missing_token',
				'needs_key'  => false,
			),
			'bearer whitespace'    => array(
				'token'      => 'Bearer   ',
				'error_code' => 'missing_token',
				'needs_key'  => false,
			),
			'not bearer format'    => array(
				'token'      => 'NotBearerFormat',
				'error_code' => 'invalid_jwt',
				'needs_key'  => false,
			),
			'invalid signature'    => array(
				'token'      => 'Bearer ' . $invalid_jwt,
				'error_code' => 'invalid_jwt',
				'needs_key'  => true,
			),
			'malformed (1 segment)' => array(
				'token'        => 'Bearer randomgarbage',
				'error_code'   => 'invalid_jwt',
				'needs_key'    => true,
			),
			'malformed (2 segments)' => array(
				'token'        => 'Bearer invalid.token',
				'error_code'   => 'invalid_jwt',
				'needs_key'    => true,
			),
			'malformed (4 segments)' => array(
				'token'        => 'Bearer a.b.c.d',
				'error_code'   => 'invalid_jwt',
				'needs_key'    => true,
			),
			'expired token'        => array(
				'token'      => 'Bearer ' . $expired_jwt,
				'error_code' => 'invalid_jwt',
				'needs_key'  => true,
			),
			'not yet valid token'  => array(
				'token'      => 'Bearer ' . $future_jwt,
				'error_code' => 'invalid_jwt',
				'needs_key'  => true,
			),
		);
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
		$result  = $service->validate_request( 'Bearer some.valid.token' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'key_unavailable', $result->get_error_code() );
		$this->assertSame( 503, $result->get_error_data()['status'] );
	}

	/**
	 * GIVEN valid Bearer token with correct signature
	 * WHEN validate_request is called
	 * THEN should return decoded stdClass payload
	 *
	 * @dataProvider bearerCaseProvider
	 */
	public function test_validate_request_returns_decoded_payload_for_valid_jwt( string $prefix ): void {
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

		$valid_jwt = JWT::encode( (array) $expected_payload, 'test-secret-key', 'HS256' );
		$token     = $prefix . ' ' . $valid_jwt;

		$result = $service->validate_request( $token );

		$this->assertInstanceOf( \stdClass::class, $result );
		$this->assertEquals( $expected_payload, $result );
	}

	public function bearerCaseProvider(): array {
		return array(
			'lowercase' => array( 'bearer' ),
			'uppercase' => array( 'BEARER' ),
			'mixedcase' => array( 'BeArEr' ),
			'standard'  => array( 'Bearer' ),
		);
	}
}
