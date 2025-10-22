<?php
/**
 * Tests for PayPal JWK (JSON Web Key) provider.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use WooCommerce\PayPalCommerce\TestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\Auth\PayPalJwkProvider
 */
class PayPalJwkProviderTest extends TestCase {

	/**
	 * GIVEN cached keys are available
	 * WHEN keys() is called
	 * THEN should return the cached keys without fetching
	 */
	public function test_returns_cached_keys_when_available(): void {
		$cached_keys = array(
			'key1' => 'value1',
			'key2' => 'value2',
		);

		$provider = $this->getMockBuilder( PayPalJwkProvider::class )
			->onlyMethods( array( 'cache_get', 'cache_set', 'fetch' ) )
			->getMock();

		$provider->method( 'cache_get' )
			->willReturn( $cached_keys );

		$provider->expects( $this->never() )
			->method( 'fetch' );

		$provider->expects( $this->never() )
			->method( 'cache_set' );

		$result = $provider->keys();

		$this->assertSame( $cached_keys, $result );
	}

	/**
	 * GIVEN no cached keys exist
	 * WHEN keys() is called
	 * THEN should fetch and return fresh keys
	 */
	public function test_fetches_and_caches_keys_when_cache_empty(): void {
		$fresh_keys = array(
			'kty' => 'RSA',
			'kid' => 'test-key-id',
			'n'   => 'test-modulus',
			'e'   => 'AQAB',
		);

		$provider = $this->getMockBuilder( PayPalJwkProvider::class )
			->onlyMethods( array( 'fetch' ) )
			->getMock();

		$provider->expects( $this->once() )
			->method( 'fetch' )
			->willReturn( $fresh_keys );

		$result = $provider->keys();

		$this->assertSame( $fresh_keys, $result );
	}
}
