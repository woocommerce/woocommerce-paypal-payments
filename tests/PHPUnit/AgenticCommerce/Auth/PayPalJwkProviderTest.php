<?php
/**
 * Tests for PayPal JWK (JSON Web Key) provider.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Auth;

use WooCommerce\PayPalCommerce\TestCase;

class PayPalJwkProviderTest extends TestCase {

	/**
	 * GIVEN PayPalJwkProvider instance
	 * WHEN keys() is called
	 * THEN should return an array
	 */
	public function test_keys_returns_array(): void {
		$provider = new PayPalJwkProvider();

		$result = $provider->keys();

		$this->assertIsArray( $result );
	}
}
