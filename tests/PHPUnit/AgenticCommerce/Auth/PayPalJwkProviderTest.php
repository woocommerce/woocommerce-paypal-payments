<?php
/**
 * Tests for PayPal JWK (JSON Web Key) provider.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use WooCommerce\PayPalCommerce\TestCase;
use Firebase\JWT\Key;
use Mockery;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\Auth\PayPalJwkProvider
 */
class PayPalJwkProviderTest extends TestCase {

	/**
	 * GIVEN cached key is available
	 * WHEN keys() is called
	 * THEN should return the cached key without fetching
	 */
	public function test_returns_cached_key_when_available(): void {
		$cached_key = new Key( 'cached-key-string', 'HS256' );

		$provider = Mockery::mock( PayPalJwkProvider::class )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$provider->shouldReceive( 'cache_get' )
			->once()
			->andReturn( $cached_key );

		$provider->shouldReceive( 'fetch_key_material' )
			->never();

		$provider->shouldReceive( 'cache_set' )
			->never();

		$result = $provider->keys();

		$this->assertInstanceOf( Key::class, $result );
		$this->assertSame( $cached_key, $result );
	}

	/**
	 * GIVEN no cached key exists
	 * WHEN keys() is called
	 * THEN should fetch key string, create Key instance, cache it, and return it
	 */
	public function test_fetches_and_caches_key_when_cache_empty(): void {
		$key_string = 'fresh-key-string';

		$provider = Mockery::mock( PayPalJwkProvider::class )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$provider->shouldReceive( 'cache_get' )
			->once()
			->andReturn( null );

		$provider->shouldReceive( 'fetch_key_material' )
			->once()
			->andReturn( $key_string );

		$provider->shouldReceive( 'cache_set' )
			->once()
			->withArgs(
				fn( $key ) => $key instanceof Key
					&& $key->getKeyMaterial() === $key_string
					&& $key->getAlgorithm() === 'RS256'
			);

		$result = $provider->keys();

		$this->assertInstanceOf( Key::class, $result );
		$this->assertSame( $key_string, $result->getKeyMaterial() );
		$this->assertSame( 'RS256', $result->getAlgorithm() );
	}

	/**
	 * GIVEN no cached key exists initially
	 * WHEN keys() is called multiple times
	 * THEN should fetch once and return cached key on subsequent calls
	 */
	public function test_caches_key_after_first_fetch(): void {
		$key_string = 'fresh-key-string';

		$provider = Mockery::mock( PayPalJwkProvider::class )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$provider->shouldReceive( 'fetch_key_material' )
			->once()
			->andReturn( $key_string );

		$result1 = $provider->keys(); // Fresh fetch.
		$this->assertInstanceOf( Key::class, $result1 );
		$this->assertSame( $key_string, $result1->getKeyMaterial() );

		$result2 = $provider->keys(); // Cache hit, no second fetch.
		$this->assertInstanceOf( Key::class, $result2 );
		$this->assertSame( $result1, $result2 );
	}

	/**
	 * GIVEN no cached key exists
	 * AND fetch returns empty string
	 * WHEN keys() is called
	 * THEN should return null without caching
	 */
	public function test_returns_null_when_fetch_returns_empty_string(): void {
		$provider = Mockery::mock( PayPalJwkProvider::class )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$provider->shouldReceive( 'cache_get' )
			->once()
			->andReturn( null );

		$provider->shouldReceive( 'fetch_key_material' )
			->once()
			->andReturn( '' );

		$provider->shouldReceive( 'cache_set' )
			->never();

		$result = $provider->keys();

		$this->assertNull( $result );
	}

	/**
	 * GIVEN no cached key exists
	 * AND fetch returns an empty string
	 * WHEN keys() is called
	 * THEN should return null without caching
	 */
	public function test_returns_null_when_fetch_returns_nothing(): void {
		$provider = Mockery::mock( PayPalJwkProvider::class )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$provider->shouldReceive( 'cache_get' )
			->once()
			->andReturn( null );

		$provider->shouldReceive( 'fetch_key_material' )
			->once()
			->andReturn( '' );

		$provider->shouldReceive( 'cache_set' )
			->never();

		$result = $provider->keys();

		$this->assertNull( $result );
	}
}
