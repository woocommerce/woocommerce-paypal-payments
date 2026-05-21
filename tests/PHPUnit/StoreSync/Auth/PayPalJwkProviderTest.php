<?php
/**
 * Tests for PayPal JWK (JSON Web Key) provider.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Auth;

use WooCommerce\PayPalCommerce\TestCase;
use Firebase\JWT\Key;
use Mockery;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Auth\PayPalJwkProvider
 */
class PayPalJwkProviderTest extends TestCase {

	private Mockery\MockInterface $provider;

	private array $valid_jwks = array(
		'keys' => array(
			array(
				'kty' => 'RSA',
				'n'   => 'test-modulus',
				'e'   => 'AQAB',
				'alg' => 'RS256',
				'kid' => 'test-key-id',
			),
		),
	);

	public function setUp(): void {
		parent::setUp();

		$this->provider = Mockery::mock( PayPalJwkProvider::class )
			->makePartial()
			->shouldAllowMockingProtectedMethods();
	}

	/**
	 * GIVEN valid JWKS data exists in cache
	 * WHEN keys() is called
	 * THEN should return parsed key without fetching remote
	 */
	public function test_returns_key_from_cache(): void {
		$this->provider->shouldReceive( 'cache_get' )
			->once()
			->andReturn( $this->valid_jwks );

		$this->provider->shouldReceive( 'fetch_jwks_from_remote' )->never();

		$result = $this->provider->keys();

		$this->assertInstanceOf( Key::class, $result );
	}

	/**
	 * GIVEN no cached data exists
	 * WHEN keys() is called
	 * THEN should fetch from remote, cache it, and return parsed key
	 */
	public function test_fetches_and_caches_on_cache_miss(): void {
		$this->provider->shouldReceive( 'cache_get' )
			->once()
			->andReturn( null );

		$this->provider->shouldReceive( 'fetch_jwks_from_remote' )
			->once()
			->andReturn( $this->valid_jwks );

		$this->provider->shouldReceive( 'cache_set' )
			->once()
			->with( $this->valid_jwks );

		$result = $this->provider->keys();

		$this->assertInstanceOf( Key::class, $result );
	}

	/**
	 * GIVEN no cached data exists
	 * AND remote fetch fails
	 * WHEN keys() is called
	 * THEN should return null without caching
	 */
	public function test_returns_null_when_remote_fetch_fails(): void {
		$this->provider->shouldReceive( 'cache_get' )
			->once()
			->andReturn( null );

		$this->provider->shouldReceive( 'fetch_jwks_from_remote' )
			->once()
			->andReturn( null );

		$this->provider->shouldReceive( 'cache_set' )->never();

		$result = $this->provider->keys();

		$this->assertNull( $result );
	}

	/**
	 * GIVEN remote returns JWKS with invalid key structure
	 * WHEN keys() is called
	 * THEN should return null
	 */
	public function test_returns_null_when_parsing_fails(): void {
		when( 'set_transient' )->justReturn( true );
		$invalid_jwks = array(
			'keys' => array(
				array( 'missing' => 'required-fields' ),
			),
		);

		$this->provider->shouldReceive( 'cache_get' )->andReturn( null );
		$this->provider->shouldReceive( 'fetch_jwks_from_remote' )
			->andReturn( $invalid_jwks );

		$result = $this->provider->keys();

		$this->assertNull( $result );
	}
}
