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
use WooCommerce\PayPalCommerce\AgenticCommerce\Merchant\MerchantMetadata;
use WooCommerce\PayPalCommerce\AgenticCommerce\Merchant\MerchantMetadataProvider;

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
		$jwk_provider      = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );

		if ( $needs_key ) {
			$key = new Key( 'test-secret-key', 'HS256' );
			$jwk_provider->shouldReceive( 'keys' )
				->once()
				->andReturn( $key );
		} else {
			$jwk_provider->shouldReceive( 'keys' )->never();
		}

		$service = new JwtAuthService( $jwk_provider, $metadata_provider );
		$result  = $service->get_token( $token );

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
			'null token'             => array(
				'token'      => null,
				'error_code' => 'missing_token',
				'needs_key'  => false,
			),
			'empty string'           => array(
				'token'      => '',
				'error_code' => 'missing_token',
				'needs_key'  => false,
			),
			'only whitespace'        => array(
				'token'      => '   ',
				'error_code' => 'missing_token',
				'needs_key'  => false,
			),
			'bearer with no token'   => array(
				'token'      => 'Bearer ',
				'error_code' => 'missing_token',
				'needs_key'  => false,
			),
			'bearer whitespace'      => array(
				'token'      => 'Bearer   ',
				'error_code' => 'missing_token',
				'needs_key'  => false,
			),
			'not bearer format'      => array(
				'token'      => 'NotBearerFormat',
				'error_code' => 'invalid_jwt',
				'needs_key'  => false,
			),
			'invalid signature'      => array(
				'token'      => 'Bearer ' . $invalid_jwt,
				'error_code' => 'invalid_jwt',
				'needs_key'  => true,
			),
			'malformed (1 segment)'  => array(
				'token'      => 'Bearer randomgarbage',
				'error_code' => 'invalid_jwt',
				'needs_key'  => true,
			),
			'malformed (2 segments)' => array(
				'token'      => 'Bearer invalid.token',
				'error_code' => 'invalid_jwt',
				'needs_key'  => true,
			),
			'malformed (4 segments)' => array(
				'token'      => 'Bearer a.b.c.d',
				'error_code' => 'invalid_jwt',
				'needs_key'  => true,
			),
			'expired token'          => array(
				'token'      => 'Bearer ' . $expired_jwt,
				'error_code' => 'invalid_jwt',
				'needs_key'  => true,
			),
			'not yet valid token'    => array(
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
		$provider          = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );

		$provider->shouldReceive( 'keys' )
			->once()
			->andReturn( null );

		$service = new JwtAuthService( $provider, $metadata_provider );
		$result  = $service->get_token( 'Bearer some.valid.token' );

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

		$provider          = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );

		$provider->shouldReceive( 'keys' )
			->once()
			->andReturn( $key );

		$service = new JwtAuthService( $provider, $metadata_provider );

		$valid_jwt = JWT::encode( (array) $expected_payload, 'test-secret-key', 'HS256' );
		$token     = $prefix . ' ' . $valid_jwt;

		$result = $service->get_token( $token );

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

	/**
	 * GIVEN valid token with correct issuer, scopes, and merchant ID
	 * WHEN verify_claims is called
	 * THEN should return true
	 */
	public function test_verify_claims_accepts_valid_token(): void {
		$token = (object) array(
			'iss'         => 'paypal.com',
			'scope'       => array( 'cart', 'checkout' ),
			'external_id' => array( 'PayPal:MERCHANT123' ),
		);

		$metadata = new MerchantMetadata(
			'Test Store',
			'https://example.com',
			'US',
			'USD',
			'MERCHANT123',
			'https://example.com'
		);

		$jwk_provider      = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );
		$metadata_provider->shouldReceive( 'get_metadata' )
			->once()
			->andReturn( $metadata );

		$service = new JwtAuthService( $jwk_provider, $metadata_provider );
		$result  = $service->verify_claims( $token, array( 'cart' ) );

		$this->assertTrue( $result );
	}

	/**
	 * GIVEN token with invalid issuer
	 * WHEN verify_claims is called
	 * THEN should return WP_Error with 'invalid_issuer' code and 401 status
	 *
	 * @dataProvider invalidIssuerProvider
	 */
	public function test_verify_claims_rejects_invalid_issuer( $issuer ): void {
		$token = (object) array(
			'iss'         => $issuer,
			'scope'       => array( 'cart' ),
			'external_id' => array( 'PayPal:MERCHANT123' ),
		);

		$jwk_provider      = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );

		$service = new JwtAuthService( $jwk_provider, $metadata_provider );
		$result  = $service->verify_claims( $token, array( 'cart' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_issuer', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function invalidIssuerProvider(): array {
		return array(
			'wrong issuer'  => array( 'evil.com' ),
			'empty string'  => array( '' ),
			'null'          => array( null ),
			'almost right'  => array( 'paypal.com.evil.com' ),
			'subdomain'     => array( 'sub.paypal.com' ),
		);
	}

	/**
	 * GIVEN token missing issuer field
	 * WHEN verify_claims is called
	 * THEN should return WP_Error with 'invalid_issuer' code and 401 status
	 */
	public function test_verify_claims_rejects_missing_issuer(): void {
		$token = (object) array(
			'scope'       => array( 'cart' ),
			'external_id' => array( 'PayPal:MERCHANT123' ),
		);

		$jwk_provider      = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );

		$service = new JwtAuthService( $jwk_provider, $metadata_provider );
		$result  = $service->verify_claims( $token, array( 'cart' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_issuer', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	/**
	 * GIVEN token with insufficient scopes
	 * WHEN verify_claims is called
	 * THEN should return WP_Error with 'insufficient_scope' code and 403 status
	 */
	public function test_verify_claims_rejects_insufficient_scopes(): void {
		$token = (object) array(
			'iss'         => 'paypal.com',
			'scope'       => array( 'cart' ),
			'external_id' => array( 'PayPal:MERCHANT123' ),
		);

		$jwk_provider      = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );

		$service = new JwtAuthService( $jwk_provider, $metadata_provider );
		$result  = $service->verify_claims( $token, array( 'cart', 'checkout' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'insufficient_scope', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * GIVEN token with malformed scopes
	 * WHEN verify_claims is called
	 * THEN should return WP_Error with 'invalid_token' code and 401 status
	 *
	 * @dataProvider malformedScopesProvider
	 */
	public function test_verify_claims_rejects_malformed_scopes( $scopes ): void {
		$token = (object) array(
			'iss'         => 'paypal.com',
			'scope'       => $scopes,
			'external_id' => array( 'PayPal:MERCHANT123' ),
		);

		$jwk_provider      = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );

		$service = new JwtAuthService( $jwk_provider, $metadata_provider );
		$result  = $service->verify_claims( $token, array( 'cart' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_token', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function malformedScopesProvider(): array {
		return array(
			'string instead of array' => array( 'cart' ),
			'object instead of array' => array( (object) array( 'cart' ) ),
			'integer'                 => array( 123 ),
		);
	}

	/**
	 * GIVEN token missing scope field
	 * WHEN verify_claims is called
	 * THEN should return WP_Error with 'insufficient_scope' code and 403 status
	 */
	public function test_verify_claims_treats_missing_scope_as_no_scopes(): void {
		$token = (object) array(
			'iss'         => 'paypal.com',
			'external_id' => array( 'PayPal:MERCHANT123' ),
		);

		$jwk_provider      = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );

		$service = new JwtAuthService( $jwk_provider, $metadata_provider );
		$result  = $service->verify_claims( $token, array( 'cart' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'insufficient_scope', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * GIVEN token with merchant ID mismatch
	 * WHEN verify_claims is called
	 * THEN should return WP_Error with 'merchant_mismatch' code and 403 status
	 */
	public function test_verify_claims_rejects_merchant_mismatch(): void {
		$token = (object) array(
			'iss'         => 'paypal.com',
			'scope'       => array( 'cart' ),
			'external_id' => array( 'PayPal:WRONG_MERCHANT' ),
		);

		$metadata = new MerchantMetadata(
			'Test Store',
			'https://example.com',
			'US',
			'USD',
			'MERCHANT123',
			'https://example.com'
		);

		$jwk_provider      = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );
		$metadata_provider->shouldReceive( 'get_metadata' )
			->once()
			->andReturn( $metadata );

		$service = new JwtAuthService( $jwk_provider, $metadata_provider );
		$result  = $service->verify_claims( $token, array( 'cart' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'merchant_mismatch', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * GIVEN token with malformed external_id
	 * WHEN verify_claims is called
	 * THEN should return WP_Error with 'invalid_token' code and 401 status
	 *
	 * @dataProvider malformedExternalIdProvider
	 */
	public function test_verify_claims_rejects_malformed_external_id( $external_id ): void {
		$token = (object) array(
			'iss'         => 'paypal.com',
			'scope'       => array( 'cart' ),
			'external_id' => $external_id,
		);

		$metadata = new MerchantMetadata(
			'Test Store',
			'https://example.com',
			'US',
			'USD',
			'MERCHANT123',
			'https://example.com'
		);

		$jwk_provider      = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );
		$metadata_provider->shouldReceive( 'get_metadata' )
			->once()
			->andReturn( $metadata );

		$service = new JwtAuthService( $jwk_provider, $metadata_provider );
		$result  = $service->verify_claims( $token, array( 'cart' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_token', $result->get_error_code() );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function malformedExternalIdProvider(): array {
		return array(
			'string instead of array' => array( 'PayPal:MERCHANT123' ),
			'object instead of array' => array( (object) array( 'PayPal:MERCHANT123' ) ),
			'integer'                 => array( 123 ),
		);
	}

	/**
	 * GIVEN token missing external_id field
	 * WHEN verify_claims is called
	 * THEN should return WP_Error with 'merchant_mismatch' code and 403 status
	 */
	public function test_verify_claims_treats_missing_external_id_as_no_merchant(): void {
		$token = (object) array(
			'iss'   => 'paypal.com',
			'scope' => array( 'cart' ),
		);

		$metadata = new MerchantMetadata(
			'Test Store',
			'https://example.com',
			'US',
			'USD',
			'MERCHANT123',
			'https://example.com'
		);

		$jwk_provider      = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );
		$metadata_provider->shouldReceive( 'get_metadata' )
			->once()
			->andReturn( $metadata );

		$service = new JwtAuthService( $jwk_provider, $metadata_provider );
		$result  = $service->verify_claims( $token, array( 'cart' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'merchant_mismatch', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * GIVEN merchant ID is not configured
	 * WHEN verify_claims is called
	 * THEN should return WP_Error with 'merchant_not_configured' code and 500 status
	 */
	public function test_verify_claims_rejects_missing_merchant_config(): void {
		$token = (object) array(
			'iss'         => 'paypal.com',
			'scope'       => array( 'cart' ),
			'external_id' => array( 'PayPal:MERCHANT123' ),
		);

		$metadata = new MerchantMetadata(
			'Test Store',
			'https://example.com',
			'US',
			'USD',
			'', // Empty merchant ID
			'https://example.com'
		);

		$jwk_provider      = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );
		$metadata_provider->shouldReceive( 'get_metadata' )
			->once()
			->andReturn( $metadata );

		$service = new JwtAuthService( $jwk_provider, $metadata_provider );
		$result  = $service->verify_claims( $token, array( 'cart' ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'merchant_not_configured', $result->get_error_code() );
		$this->assertSame( 500, $result->get_error_data()['status'] );
	}
}
