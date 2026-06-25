<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\StoreSync;

use WooCommerce\PayPalCommerce\StoreSync\Auth\PayPalJwkProvider;
use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;
use Firebase\JWT\Key;

/**
 * Integration tests for PayPal JWK Provider.
 * These tests make REAL HTTP requests to PayPal's servers.
 *
 * @group external
 * @group integration
 */
class PayPalJwkProviderTest extends TestCase {
	private PayPalJwkProvider $provider;
	private string $transient_name = 'ppcp-ai-jwks';

	public function setUp(): void {
		parent::setUp();

		$this->container = $this->getContainer();
		$this->provider  = $this->container->get( 'agentic.auth.key_provider' );

		// Clear transient before each test to force fresh fetch.
		delete_transient( $this->transient_name );
	}

	public function tearDown(): void {
		delete_transient( $this->transient_name );
		parent::tearDown();
	}

	/**
	 * GIVEN PayPal's JWKS endpoint is accessible
	 * WHEN keys() is called
	 * THEN should return a non-empty array of Firebase\JWT\Key instances keyed by kid
	 */
	public function test_fetches_real_paypal_jwks(): void {
		$result = $this->provider->keys();

		$this->assertIsArray( $result, 'Failed to fetch and parse real PayPal JWKS. Check if https://www.paypal.ai/.well-known/jwks.json is accessible.' );
		$this->assertNotEmpty( $result, 'JWKS result must contain at least one key.' );
		$this->assertContainsOnlyInstancesOf( Key::class, $result );
	}

	/**
	 * GIVEN real JWKS was fetched successfully
	 * WHEN checking the cached data
	 * THEN should have valid structure matching PayPal's format
	 */
	public function test_cached_jwks_has_valid_structure(): void {
		// Force a fetch.
		$this->provider->keys();

		$cached = get_transient( $this->transient_name );

		$this->assertIsArray( $cached, 'Cached JWKS should be an array' );
		$this->assertArrayHasKey( 'keys', $cached, 'Cached JWKS should have "keys" array' );
		$this->assertNotEmpty( $cached['keys'], 'Keys array should not be empty' );

		$first_key = $cached['keys'][0];

		// Verify PayPal's expected key structure.
		$this->assertArrayHasKey( 'kty', $first_key, 'Key should have "kty" (key type)' );
		$this->assertArrayHasKey( 'n', $first_key, 'Key should have "n" (modulus)' );
		$this->assertArrayHasKey( 'e', $first_key, 'Key should have "e" (exponent)' );
		$this->assertArrayHasKey( 'alg', $first_key, 'Key should have "alg" (algorithm)' );

		$this->assertEquals( 'RSA', $first_key['kty'], 'Key type should be RSA' );
		$this->assertEquals( 'RS256', $first_key['alg'], 'Algorithm should be RS256' );
	}

	/**
	 * GIVEN JWKS was cached from previous fetch
	 * WHEN keys() is called again within cache TTL
	 * THEN should return keys from cache without new HTTP request
	 */
	public function test_uses_cache_on_subsequent_calls(): void {
		// First call - fetches from remote.
		$result1 = $this->provider->keys();
		$this->assertNotEmpty( $result1 );
		$this->assertContainsOnlyInstancesOf( Key::class, $result1 );

		$cached_before = get_transient( $this->transient_name );

		// Second call - should use cache.
		$result2 = $this->provider->keys();
		$this->assertNotEmpty( $result2 );
		$this->assertContainsOnlyInstancesOf( Key::class, $result2 );

		$cached_after = get_transient( $this->transient_name );

		// Verify cache wasn't modified (no new fetch occurred).
		$this->assertEquals(
			$cached_before,
			$cached_after,
			'Cache should remain unchanged on subsequent calls'
		);
	}

	/**
	 * GIVEN cache is cleared
	 * WHEN keys() is called
	 * THEN should successfully refetch from PayPal
	 */
	public function test_refetches_after_cache_clear(): void {
		// Initial fetch.
		$result1 = $this->provider->keys();
		$this->assertNotEmpty( $result1 );

		// Clear cache.
		delete_transient( $this->transient_name );
		$this->assertFalse( get_transient( $this->transient_name ) );

		// Should refetch successfully.
		$result2 = $this->provider->keys();
		$this->assertNotEmpty( $result2 );
		$this->assertContainsOnlyInstancesOf( Key::class, $result2 );

		// Verify cache was repopulated.
		$this->assertNotFalse( get_transient( $this->transient_name ) );
	}

	/**
	 * GIVEN PayPal returns valid key material
	 * WHEN parsing the keys
	 * THEN every parsed Key object should have RS256 algorithm
	 */
	public function test_parsed_key_has_correct_algorithm(): void {
		$keys = $this->provider->keys();

		$this->assertNotEmpty( $keys );
		foreach ( $keys as $key ) {
			$this->assertInstanceOf( Key::class, $key );
			$this->assertEquals(
				'RS256',
				$key->getAlgorithm(),
				'Parsed key should use RS256 algorithm'
			);
		}
	}

	/**
	 * GIVEN transient cache has been corrupted with invalid data
	 * WHEN keys() is called
	 * THEN should detect corruption and refetch from PayPal
	 */
	public function test_recovers_from_corrupted_cache(): void {
		// Corrupt the cache.
		set_transient( $this->transient_name, 'corrupted-data', DAY_IN_SECONDS );

		// Should still work by refetching.
		$result = $this->provider->keys();

		$this->assertNotEmpty( $result, 'Should recover from corrupted cache by refetching' );
		$this->assertContainsOnlyInstancesOf( Key::class, $result );

		// Verify cache was fixed.
		$cached = get_transient( $this->transient_name );
		$this->assertIsArray( $cached );
		$this->assertArrayHasKey( 'keys', $cached );
	}
}
