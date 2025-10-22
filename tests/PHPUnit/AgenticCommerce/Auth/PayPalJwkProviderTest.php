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
			->onlyMethods( array( 'get_cached', 'fetch_keys' ) )
			->getMock();

		$provider->method( 'get_cached' )
			->willReturn( $cached_keys );

		$provider->expects( $this->never() )
			->method( 'fetch_keys' );

		$result = $provider->keys();

		$this->assertSame( $cached_keys, $result );

		$result = $provider->keys();

		$this->assertSame( $fresh_keys, $result );
	}
}
