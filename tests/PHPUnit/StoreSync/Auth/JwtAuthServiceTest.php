<?php
/**
 * Tests for JWT authentication service.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Auth;

use WooCommerce\PayPalCommerce\TestCase;
use WP_Error;
use Mockery;
use Firebase\JWT\Key;
use Firebase\JWT\JWT;
use WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadata;
use WooCommerce\PayPalCommerce\StoreSync\Merchant\MerchantMetadataProvider;
use stdClass;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Auth\JwtAuthService
 */
class JwtAuthServiceTest extends TestCase {

	/**
	 * Creates a JwtAuthService with mocked dependencies.
	 *
	 * @param MerchantMetadata|null $metadata Optional metadata to return.
	 * @param Key|null              $jwk_key  Optional JWK key to return.
	 * @return JwtAuthService
	 */
	private function create_service( ?MerchantMetadata $metadata = null, ?Key $jwk_key = null ): JwtAuthService {
		$jwk_provider      = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );

		$jwk_provider->allows( 'keys' )
			->andReturn( $jwk_key );
		$metadata_provider->allows( 'get_metadata' )
			->andReturn( $metadata );

		return new JwtAuthService( $jwk_provider, $metadata_provider );
	}

	/**
	 * Creates a default test merchant metadata.
	 *
	 * @param string $merchant_id Merchant ID to use.
	 * @return MerchantMetadata
	 */
	private function create_metadata( string $merchant_id = 'MERCHANT123' ): MerchantMetadata {
		return new MerchantMetadata(
			'Test Store',
			'https://example.com',
			'https://example.com/wp-json/wc/store/v1',
			'US',
			'USD',
			$merchant_id,
			'https://example.com',
			'CA'
		);
	}

	/**
	 * Creates a token object with specified claims.
	 *
	 * @param array $claims Token claims.
	 * @return object
	 */
	private function create_token( array $claims ): object {
		return (object) array_merge(
			array(
				'iss'         => 'paypal.com',
				'scope'       => array( 'cart' ),
				'external_id' => array( 'PayPal:MERCHANT123' ),
			),
			$claims
		);
	}

	/**
	 * Asserts that result is a WP_Error with expected code and HTTP status.
	 *
	 * @param mixed  $result      Result to check.
	 * @param string $error_code  Expected error code.
	 * @param int    $http_status Expected HTTP status.
	 */
	private function assert_error_response( $result, string $error_code, int $http_status ): void {
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( $error_code, $result->get_error_code() );
		$this->assertSame( $http_status, $result->get_error_data()['status'] );
	}

	/**
	 * GIVEN invalid or missing tokens
	 * WHEN get_token is called
	 * THEN should return WP_Error with appropriate code and HTTP status
	 *
	 * @dataProvider invalidTokenProvider
	 */
	public function test_get_token_rejects_invalid_tokens(
		?string $token,
		string $expected_error_code,
		int $expected_status,
		bool $needs_key
	): void {
		$key     = $needs_key ? new Key( 'test-secret-key', 'HS256' ) : null;
		$service = $this->create_service( null, $key );
		$result  = $service->get_token( $token );

		$this->assert_error_response( $result, $expected_error_code, $expected_status );
	}

	public function invalidTokenProvider(): array {
		$invalid_jwt =
			'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.invalid_signature';
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
			// Missing token scenarios (401).
			'null token'             => array( null, 'missing_token', 401, false ),
			'empty string'           => array( '', 'missing_token', 401, false ),
			'only whitespace'        => array( '   ', 'missing_token', 401, false ),
			'bearer with no token'   => array( 'Bearer ', 'missing_token', 401, false ),
			'bearer whitespace'      => array( 'Bearer   ', 'missing_token', 401, false ),

			// Format and validation errors (401).
			'not bearer format'      => array( 'NotBearerFormat', 'invalid_jwt', 401, false ),
			'invalid signature'      => array( 'Bearer ' . $invalid_jwt, 'invalid_jwt', 401, true ),
			'malformed (1 segment)'  => array( 'Bearer randomgarbage', 'invalid_jwt', 401, true ),
			'malformed (2 segments)' => array( 'Bearer invalid.token', 'invalid_jwt', 401, true ),
			'malformed (4 segments)' => array( 'Bearer a.b.c.d', 'invalid_jwt', 401, true ),
			'expired token'          => array( 'Bearer ' . $expired_jwt, 'invalid_jwt', 401, true ),
			'not yet valid token'    => array( 'Bearer ' . $future_jwt, 'invalid_jwt', 401, true ),
		);
	}

	/**
	 * GIVEN provider returns null (key unavailable)
	 * WHEN get_token is called with valid Bearer token
	 * THEN should return WP_Error with 'key_unavailable' code and 503 status
	 */
	public function test_get_token_handles_unavailable_key(): void {
		$jwk_provider      = Mockery::mock( PayPalJwkProvider::class );
		$metadata_provider = Mockery::mock( MerchantMetadataProvider::class );

		$jwk_provider->allows( 'keys' )
			->andReturn( null );

		$service = new JwtAuthService( $jwk_provider, $metadata_provider );
		$result  = $service->get_token( 'Bearer some.valid.token' );

		$this->assert_error_response( $result, 'key_unavailable', 503 );
	}

	/**
	 * GIVEN valid Bearer token with correct signature
	 * WHEN get_token is called
	 * THEN should return decoded stdClass payload
	 *
	 * @dataProvider bearerCaseProvider
	 */
	public function test_get_token_returns_decoded_payload_for_valid_jwt( string $prefix ): void {
		$expected_payload = (object) array(
			'sub'  => '1234567890',
			'name' => 'John Doe',
			'iat'  => 1516239022,
		);

		$key     = new Key( 'test-secret-key', 'HS256' );
		$service = $this->create_service( null, $key );

		$valid_jwt = JWT::encode( (array) $expected_payload, 'test-secret-key', 'HS256' );
		$token     = $prefix . ' ' . $valid_jwt;

		$result = $service->get_token( $token );

		$this->assertInstanceOf( stdClass::class, $result );
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
		$token    = $this->create_token( array( 'scope' => array( 'cart', 'checkout' ) ) );
		$metadata = $this->create_metadata();
		$service  = $this->create_service( $metadata );

		$result = $service->verify_claims( $token, array( 'cart' ) );

		$this->assertTrue( $result );
	}

	/**
	 * GIVEN token with validation errors
	 * WHEN verify_claims is called
	 * THEN should return WP_Error with appropriate code and status
	 *
	 * @dataProvider claimValidationErrorProvider
	 */
	public function test_verify_claims_rejects_invalid_claims(
		array $token_data,
		?string $merchant_id,
		string $error_code,
		int $http_status
	): void {
		$token   = $this->create_token( $token_data );
		$service = $this->create_service(
			$merchant_id !== null ? $this->create_metadata( $merchant_id ) : null
		);

		$result = $service->verify_claims( $token, array( 'cart' ) );

		$this->assert_error_response( $result, $error_code, $http_status );
	}

	public function claimValidationErrorProvider(): array {
		return array(
			// Issuer validation (401) - Must be exactly "paypal.com".
			'wrong issuer'            => array(
				'token_data'  => array( 'iss' => 'evil.com' ),
				'merchant_id' => null,
				'error_code'  => 'invalid_issuer',
				'http_status' => 401,
			),
			'empty issuer'            => array(
				'token_data'  => array( 'iss' => '' ),
				'merchant_id' => null,
				'error_code'  => 'invalid_issuer',
				'http_status' => 401,
			),
			'issuer subdomain'        => array(
				'token_data'  => array( 'iss' => 'sub.paypal.com' ),
				'merchant_id' => null,
				'error_code'  => 'invalid_issuer',
				'http_status' => 401,
			),

			// Scope validation (401/403).
			'insufficient scopes'     => array(
				'token_data'  => array( 'scope' => array() ),
				'merchant_id' => null,
				'error_code'  => 'insufficient_scope',
				'http_status' => 403,
			),

			// Type validation (401) - scope and external_id must be arrays.
			'scope not array'         => array(
				'token_data'  => array( 'scope' => 'cart' ),
				'merchant_id' => null,
				'error_code'  => 'invalid_token',
				'http_status' => 401,
			),
			'external_id not array'   => array(
				'token_data'  => array( 'external_id' => 'PayPal:MERCHANT123' ),
				'merchant_id' => 'MERCHANT123',
				'error_code'  => 'invalid_token',
				'http_status' => 401,
			),

			// Merchant validation (403/500).
			'merchant mismatch'       => array(
				'token_data'  => array( 'external_id' => array( 'PayPal:WRONG_MERCHANT' ) ),
				'merchant_id' => 'MERCHANT123',
				'error_code'  => 'merchant_mismatch',
				'http_status' => 403,
			),
			'missing external_id'     => array(
				'token_data'  => array( 'external_id' => null ),
				'merchant_id' => 'MERCHANT123',
				'error_code'  => 'merchant_mismatch',
				'http_status' => 403,
			),
			'merchant not configured' => array(
				'token_data'  => array(),
				'merchant_id' => '',
				'error_code'  => 'merchant_not_configured',
				'http_status' => 500,
			),
		);
	}
}
