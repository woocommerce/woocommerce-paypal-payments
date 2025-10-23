<?php
/**
 * Tests for PayPal JWK (JSON Web Key) provider.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use WooCommerce\PayPalCommerce\TestCase;
use Firebase\JWT\Key;
use Mockery;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\Auth\PayPalJwkProvider
 */
class PayPalJwkProviderTest extends TestCase {

	public function setUp(): void {
		parent::setUp();

		when( 'get_transient' )->justReturn( false );
		when( 'set_transient' )->justReturn( false );
	}

	/**
	 * GIVEN cached key is available
	 * WHEN keys() is called
	 * THEN should return the cached key without fetching
	 */
	public function test_returns_cached_key_when_available(): void {
		$cached_key = new Key( 'cached-key-string', 'RS256' );

		$provider = Mockery::mock( PayPalJwkProvider::class )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$provider->shouldReceive( 'cache_get' )
			->once()
			->andReturn( $cached_key );

		$provider->shouldReceive( 'fetch_key' )->never();

		$provider->shouldReceive( 'cache_set' )->never();

		$result = $provider->keys();

		$this->assertInstanceOf( Key::class, $result );
		$this->assertSame( $cached_key, $result );
	}

	/**
	 * GIVEN no cached key exists
	 * WHEN keys() is called
	 * THEN should fetch key material and handle result appropriately
	 *
	 * @dataProvider fetchScenarioProvider
	 */
	public function test_handles_fetch_scenarios(
		?Key $key_material,
		bool $should_cache
	): void {
		$provider = Mockery::mock( PayPalJwkProvider::class )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$provider->shouldReceive( 'cache_get' )
			->once()
			->andReturn( null );

		$provider->shouldReceive( 'fetch_key' )
			->once()
			->andReturn( $key_material );

		if ( $should_cache ) {
			$provider->shouldReceive( 'cache_set' )
				->once()
				->with( $key_material );
		} else {
			$provider->shouldReceive( 'cache_set' )->never();
		}

		$result = $provider->keys();

		if ( $should_cache ) {
			$this->assertInstanceOf( Key::class, $result );
			$this->assertSame( $key_material, $result );
		} else {
			$this->assertNull( $result );
		}
	}

	public function fetchScenarioProvider(): array {
		return array(
			'successful fetch' => array(
				'key_material' => new Key( 'fresh-key-string', 'RS256' ),
				'should_cache' => true,
			),
			'null fetch'       => array(
				'key_material' => null,
				'should_cache' => false,
			),
		);
	}

	/**
	 * GIVEN no cached key exists initially
	 * WHEN keys() is called multiple times
	 * THEN should fetch once and return cached key on subsequent calls
	 */
	public function test_caches_key_after_first_fetch(): void {
		$fetched_key = new Key( 'fresh-key-string', 'RS256' );

		$provider = Mockery::mock( PayPalJwkProvider::class )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$provider->shouldReceive( 'fetch_key' )
			->once()
			->andReturn( $fetched_key );

		$result1 = $provider->keys();
		$this->assertInstanceOf( Key::class, $result1 );
		$this->assertSame( $fetched_key, $result1 );

		$result2 = $provider->keys();
		$this->assertInstanceOf( Key::class, $result2 );
		$this->assertSame( $result1, $result2 );
	}
}
