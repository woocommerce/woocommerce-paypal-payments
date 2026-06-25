<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Mockery;
use Psr\Log\LoggerInterface;
use Requests_Utility_CaseInsensitiveDictionary;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\ApiClient\Endpoint\LoginSeller
 */
class LoginSellerTest extends TestCase
{
	private const HOST = 'https://api.paypal.com';

	/**
	 * @scenario The seller_nonce passed to credentials_for() is used directly as
	 *           code_verifier in the PayPal oauth2/token request body.
	 */
	public function test_credentials_for_uses_seller_nonce_as_code_verifier(): void
	{
		// Arrange
		$seller_nonce = bin2hex( random_bytes( 32 ) );

		when( 'trailingslashit' )->justReturn( self::HOST . '/' );
		when( 'is_wp_error' )->justReturn( false );
		when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );

		$captured_code_verifier = null;
		$headers                = Mockery::mock( Requests_Utility_CaseInsensitiveDictionary::class );
		$headers->shouldReceive( 'getAll' )->andReturn( array() );

		expect( 'wp_remote_get' )
			->twice()
			->andReturnUsing(
				function ( string $url, array $args ) use ( &$captured_code_verifier, $headers ): array {
					if ( strpos( $url, 'oauth2/token' ) !== false ) {
						$captured_code_verifier = $args['body']['code_verifier'] ?? null;
						return array( 'body' => '{"access_token":"tok_test"}', 'headers' => $headers );
					}
					return array( 'body' => '{"client_id":"cid","client_secret":"csec"}', 'headers' => $headers );
				}
			);

		$logger = Mockery::mock( LoggerInterface::class );
		$logger->shouldReceive( 'debug' )->byDefault();
		$sut = new LoginSeller( self::HOST, 'partner_merchant_id', $logger );

		// When
		$sut->credentials_for( 'shared_id', 'auth_code', $seller_nonce );

		// Then
		$this->assertSame( $seller_nonce, $captured_code_verifier );
	}
}