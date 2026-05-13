<?php
/**
 * Tests for PayPal JWK (JSON Web Key) provider.
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Auth;

use WooCommerce\PayPalCommerce\TestCase;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Mockery;
use function Brain\Monkey\Functions\when;

/**
 * A testable subclass that injects a fixture JWKS instead of fetching from remote or cache.
 */
class FixtureJwkProvider extends PayPalJwkProvider {

	/** @var array */
	private $fixture_jwks;

	/**
	 * @param array $fixture_jwks
	 */
	public function __construct( array $fixture_jwks ) {
		$this->fixture_jwks = $fixture_jwks;
	}

	protected function get_jwks_data(): ?array {
		return $this->fixture_jwks;
	}
}

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

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
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

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result );
	}

	/**
	 * GIVEN no cached data exists
	 * AND remote fetch fails
	 * WHEN keys() is called
	 * THEN should return empty array without caching
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

		$this->assertSame( array(), $result );
	}

	/**
	 * GIVEN remote returns JWKS with invalid key structure
	 * WHEN keys() is called
	 * THEN should return empty array
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

		$this->assertSame( array(), $result );
	}

	// ---------------------------------------------------------------------------
	// Full-keyset regression test
	// ---------------------------------------------------------------------------

	/**
	 * Two pre-generated 2048-bit RSA private keys (PKCS#8 PEM).
	 * Generated once; stored as static fixtures to avoid runtime key generation.
	 */
	private static function rsa_private_key_1(): string {
		return "-----BEGIN PRIVATE KEY-----\n" .
			"MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQCVtnxFGqZVq5PB\n" .
			"slsvYNbYdWUBgmWeRortCcL1Mp5mfUkPgSh8nQcL36lWk+Lh/0b8bTEhGUtK/O1y\n" .
			"q2SbSlryaLzeN6aIVJ6NyPIk2WoE067GBrjy0gAnknG19//G3irOsSt9sJlEHUHZ\n" .
			"sa8hcd0lQbXtblK9lVCOfGLkMZIbisOLOGOBINZIafzkrFbupfSJ5T9akqSklfTT\n" .
			"soREAQ/3hQ16CHyINU18ERpaquYJE//wE3MC7Y2dkH2I1WErt4voHGyASG8ZrfFg\n" .
			"7n27V9cObYKhNSl+5Dh7XehaDVTYMlKNEk/6FMeAyjUu2s0f44oPyxC1LTmbWCKo\n" .
			"RMDsUjttAgMBAAECggEAMWu2BhHQCsPC2NQJqdAr2/SUzTTAWskqnyjzxIFbLLX6\n" .
			"4jVeqhQj+VVxzvSulq/wPO/Gogh5hF2N/KZ7ZY/cCd1tS2XPgX19gYxt8qAakVFX\n" .
			"hmM9tlqAVo/PHaSqOHmqY+S0WJ9tWMDXtgcjqsStndjdN22NvquFliOVeYKrmo9o\n" .
			"w3jtjrHG+khlmoPF7NBrLEHrKGW1TFC2tm4xtHsIcblxSy/odrR2YeWqFz0uEU6X\n" .
			"YCLHQCWOcz8LCPziXnP33o1h5Ih/Rn/1ACeWJ08u2YzxsDfJXTUGGJ0HX6rYCNeR\n" .
			"ECKhShJWaXX2WXKaaQVi7nXLPaPVqauzaW894WUeOQKBgQDJeitlqy309BWqKUqM\n" .
			"AYj28IiJ7sZv8ma7EdoijCJrYxVS7nkXiox9sCpzln8LjFI8rL4X9UkMgKwbZGl3\n" .
			"mG9hJ3eXPwTGSUwCAEHRnuiPg4u1VvHuTrTlU5uSCYzSjuvtD2So4FkMCDG4mfJ4\n" .
			"Aw3mzJr1L+8Ovr/xWQiYiQofIwKBgQC+OjXZ8C3WUuSkfR95XVvxi3sOVQtBwneD\n" .
			"s4o7xd8a81ELgCmP1/KjXsxPkYKTVxiEEKNYCWWRdPYC9Jez9Hnc3C1DzUtIRElH\n" .
			"0V4AWmCs5bFRgDRltH/YqXw8vxe9t2ZS2GU9fhVgBrtfjMdX1raWgg+kAvhP8+/p\n" .
			"TX/mNXmsLwKBgCRPWzDYd9DUiG8BQAkZYbi3QrQxDxwvwGnoXrqpLK7TzY0Do1kl\n" .
			"xAoGzK/GKKFJKaz7qMqijwaszdel8gf2teP5e+kLF24w2Xzm1PXVQK5Uk8IbqEA9\n" .
			"eQZ3Wesow3NTBJvVkVuKCyJK+8L8I6GTU1cL+sVDXT74C5mQZScwU12nAoGAIhdK\n" .
			"iVk6zbsjULs/xb9Od/ZYQlRJZSqVwpuNfXLTrf/HGXmJeUbpLBAUK3pXXVJxiVF2\n" .
			"BJQCiNPeNt9gxJZetI8c6ZbEFBpwy5cg8o0/4Bx177Y7LbLwaoLNShGxDoXsp5Iy\n" .
			"apfK+t+Z+uC+5OYM6OI8LVd+6s07xKLn9fjFam8CgYBUBN+H8Q8nTtSsUqDwoeFf\n" .
			"kJWk56Zy5UN4t4twE1ThrS1q/x3mtwG1XrEZ5b9XNRZQ4abn0s4C1wyqxH2EAvPg\n" .
			"SJtidLzNhThb/rc/U+Z/+3EzviAHLRkRJEKdn5W5w9ohow8sx0tgc5rBspONHdaU\n" .
			"/Fg/Nk2+bNnApuumCNQDig==\n" .
			"-----END PRIVATE KEY-----\n";
	}

	private static function rsa_private_key_2(): string {
		return "-----BEGIN PRIVATE KEY-----\n" .
			"MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC2a9X92HI1OIHU\n" .
			"f78B0nq6+Kkp+NjXvQFTxOtlPp+XC0uAXTF+a4Ss+6JpS3bPhb7LCWR7hI0JXWxp\n" .
			"fIgcFQdXogWDc1idPKmTRG81Ie3jEtqc8VF3mB4DNrPw0x3GvEdPUiLc2ngZMuFL\n" .
			"Nfn3QZIoJlngf/FOONBhx6DGa3CKWzJbPIAiQ6Wx49YzZvL034HOmp4axUb1LK9v\n" .
			"LVSD0pG3CZMxyn4SrquHKlmj6XfXGPbgxjsWHKAw5OEyNYu5L5Czy8T9WollJiLp\n" .
			"EJLKEo0BdDTvQnDF9geFb7xnewWuHsKNRKH9gwJ5Q1gDnLNCyVLoXuBLtCfVo68g\n" .
			"U7vSsHyPAgMBAAECggEAD3LGZn7j/RnR9Nr0pATdG+Re/wzx4CNDb4KnpKVPAo2U\n" .
			"UUSTIm+chsGwmVYos55R8APVnJg3EWn1+mDvbeFiA8vWx7EG+hnfCnerK0a6TJaW\n" .
			"KOBO09/v+rmxN13JkcD0EQWVqjpeHVQvvBzQFF5bMEc/KUHsf4FUNFOhi+whVbYK\n" .
			"x6c4iVuBaBN1AhyKwrK40ONWT0Vy7gWnOjxX/CSV79wkBYCjniNV0seXyjw5jzBp\n" .
			"I9sc5DU9TQf4GKjwBIHN3Wir1CqQ9EcJsMAJWu6SBPPgiPvgApbH+rl+Tbja33S6\n" .
			"ti/bkGGjgLHEUKH1GaL8FmHHQ2FeujoUJe8hoRneQQKBgQDf+BgFjk4GC2r6g7dO\n" .
			"P6BtwlGji8UP1xIAZuZckw+lBtnDBPKzR+jbGtKOtnGJVNy5d/6A/Gfpz0SlppZ2\n" .
			"ZS5u74BrAchsKInL4DuIBO0ybL3eaXpXiAU5phpjEvxMQwZgXzweFxaPXfMWBhTA\n" .
			"4QLs1fNpGQeU5DMNUArzSPMiEQKBgQDQgpm5AY2evz+E/TLgzJwu6VB8+afUECWy\n" .
			"RCswvtOeWHOIJIJ/2O+DkmC3AlEaWweMdQm+vo39N/Yo4aUkBZklfkAKJVfCVFHk\n" .
			"1gOIR97r5nPZBRIhnZHZtBdmd1AcBYs61o1HGEbQ6XBQo9lq+W8MbpYCoUFfNUzp\n" .
			"xRARf7UUnwKBgQCEgu37g4moS+Mcmwe+VSjfJ8RTpiOOzqnI8RjElwH/msEGgIv0\n" .
			"BMzBren8I/ei0EHTvionOK9mh4pPE/Qb0puZaTyqkyB41bdJl77BKGEKn4nq6K9I\n" .
			"0KJ+zEb6bUY2/MTuCgqwpupjIqvrUOfAgqDPbXqZqQRyVF3cN4pzDKtFcQKBgDZy\n" .
			"M+PMVQej1tlKKHPs2ceiIuNPaZSFVuKSzFhhK+8IF7rwFad+pSRNH7YKA9WG+ZSi\n" .
			"pxXIuljpuPx5115tm8zfh6dekujqjavcenWmlr4wogWEPnTKqWAYl5epBiEbDX0i\n" .
			"sydiXnOE0VAtSMOXOHkdk0xCgUh0KY5NZ+G54DXvAoGAZfjZM+KPXjN7GQcnbhKB\n" .
			"uGO0IHLTmaO9Clyz7/SZI9jT0+rWerXkqMsKYTmZvnE6fzZV+5BQ8SoQdvAcE8/M\n" .
			"zpizdg7ZszkMBFXKf80Q+22j2npNGcx7UGxalgeETCOuLOyoZhQqIfCZFW8FSWp2\n" .
			"NGa5riyDj9G5TqejoCeL9xs=\n" .
			"-----END PRIVATE KEY-----\n";
	}

	/**
	 * Extracts the JWK n (modulus) and e (exponent) components from a PEM private key.
	 *
	 * @param string $pem PEM-encoded private key.
	 * @return array Associative array with 'n' and 'e' as base64url strings.
	 */
	private static function jwk_components_from_pem( string $pem ): array {
		$res     = openssl_pkey_get_private( $pem );
		$details = openssl_pkey_get_details( $res );
		return array(
			'n' => rtrim( strtr( base64_encode( $details['rsa']['n'] ), '+/', '-_' ), '=' ),
			'e' => rtrim( strtr( base64_encode( $details['rsa']['e'] ), '+/', '-_' ), '=' ),
		);
	}

	/**
	 * Returns a JWKS fixture containing two RSA keys.
	 *
	 * @return array
	 */
	private static function two_key_jwks(): array {
		$key1_components = self::jwk_components_from_pem( self::rsa_private_key_1() );
		$key2_components = self::jwk_components_from_pem( self::rsa_private_key_2() );

		return array(
			'keys' => array(
				array(
					'kty' => 'RSA',
					'kid' => 'key-1',
					'use' => 'sig',
					'alg' => 'RS256',
					'n'   => $key1_components['n'],
					'e'   => $key1_components['e'],
				),
				array(
					'kty' => 'RSA',
					'kid' => 'key-2',
					'use' => 'sig',
					'alg' => 'RS256',
					'n'   => $key2_components['n'],
					'e'   => $key2_components['e'],
				),
			),
		);
	}

	/**
	 * GIVEN a JWKS endpoint that advertises two RSA keys
	 * WHEN keys() is called
	 * THEN both keys must be present in the returned keyset
	 * AND a JWT signed with the second key must be decodable using the keyset
	 */
	public function test_keys_returns_full_keyset_allowing_jwt_signed_with_second_key(): void {
		$jwks     = self::two_key_jwks();
		$provider = new FixtureJwkProvider( $jwks );

		// Encode a JWT using the second key's private key, with kid='key-2'.
		$payload = array(
			'sub'  => 'regression-test',
			'name' => 'second-key',
			'iat'  => 1700000000,
		);

		$private_key_2 = openssl_pkey_get_private( self::rsa_private_key_2() );
		$token         = JWT::encode( $payload, $private_key_2, 'RS256', 'key-2' );

		$result = $provider->keys();

		// We assert the contract: any key in the keyset must allow decoding the JWT.
		$this->assertIsArray( $result, 'keys() must return an array of Key objects, not a single Key' );
		$this->assertCount( 2, $result, 'Both keys from the JWKS must be present in the returned keyset' );

		$decoded = JWT::decode( $token, $result );

		$this->assertSame( 'regression-test', $decoded->sub );
		$this->assertSame( 'second-key', $decoded->name );
	}
}
