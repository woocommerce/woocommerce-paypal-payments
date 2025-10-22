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
		$cached_key = new Key( 'cached-key-string', 'RS256' );

		$provider = Mockery::mock( PayPalJwkProvider::class )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$provider->shouldReceive( 'cache_get' )
			->once()
			->andReturn( $cached_key );

		$provider->shouldReceive( 'fetch_key_material' )->never();

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
		string $key_material,
		bool $should_cache,
		?string $expected_algorithm
	): void {
		$provider = Mockery::mock( PayPalJwkProvider::class )
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		$provider->shouldReceive( 'cache_get' )
			->once()
			->andReturn( null );

		$provider->shouldReceive( 'fetch_key_material' )
			->once()
			->andReturn( $key_material );

		if ( $should_cache ) {
			$provider->shouldReceive( 'cache_set' )
				->once()
				->withArgs(
					fn( $key ) => $key instanceof Key
						&& $key->getKeyMaterial() === $key_material
						&& $key->getAlgorithm() === $expected_algorithm
				);
		} else {
			$provider->shouldReceive( 'cache_set' )->never();
		}

		$result = $provider->keys();

		if ( $should_cache ) {
			$this->assertInstanceOf( Key::class, $result );
			$this->assertSame( $key_material, $result->getKeyMaterial() );
			$this->assertSame( $expected_algorithm, $result->getAlgorithm() );
		} else {
			$this->assertNull( $result );
		}
	}

	public function fetchScenarioProvider(): array {
		return array(
			'successful fetch'   => array(
				'key_material'       => 'fresh-key-string',
				'should_cache'       => true,
				'expected_algorithm' => 'RS256',
			),
			'empty string fetch' => array(
				'key_material'       => '',
				'should_cache'       => false,
				'expected_algorithm' => null,
			),
		);
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

		$result1 = $provider->keys();
		$this->assertInstanceOf( Key::class, $result1 );
		$this->assertSame( $key_string, $result1->getKeyMaterial() );

		$result2 = $provider->keys();
		$this->assertInstanceOf( Key::class, $result2 );
		$this->assertSame( $result1, $result2 );
	}
}
