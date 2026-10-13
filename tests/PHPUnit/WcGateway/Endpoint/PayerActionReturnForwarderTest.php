<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use Mockery;
use WooCommerce\PayPalCommerce\Http\RedirectorInterface;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;

use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\WcGateway\Endpoint\PayerActionReturnForwarder
 */
class PayerActionReturnForwarderTest extends TestCase
{
	private const RETURN_URL = 'https://example.com/ppc-return-url';

	/** @var string[] */
	private array $redirects = [];

	private string $stored_order_id = '';

	private PayerActionReturnForwarder $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->redirects       = [];
		$this->stored_order_id = '';

		when( 'home_url' )->alias( static function ( string $path = '' ): string {
			return 'https://example.com/' . ltrim( $path, '/' );
		} );
		when( 'add_query_arg' )->alias( static function ( array $args, string $url ): string {
			return $url . '?' . http_build_query( $args );
		} );

		$session = Mockery::mock( SessionHandler::class );
		$session->shouldReceive( 'payer_action_order_id' )->andReturnUsing(
			function (): string {
				return $this->stored_order_id;
			}
		);

		$redirector = $this->createStub( RedirectorInterface::class );
		$redirector->method( 'redirect' )->willReturnCallback(
			function ( string $location ): void {
				$this->redirects[] = $location;
			}
		);

		$this->sut = new PayerActionReturnForwarder( $session, $redirector );
	}

	public function tearDown(): void
	{
		$_GET = [];
		parent::tearDown();
	}

	/**
	 * GIVEN the buyer was sent to PayPal to re-approve a stored order
	 * WHEN PayPal returns the buyer to a front-end page with that order ID as token
	 * THEN the buyer is forwarded to the return URL endpoint
	 * AND the token and the PayerID are kept in the URL
	 */
	public function test_forwards_buyer_to_return_endpoint_when_token_matches_stored_order(): void
	{
		$this->stored_order_id = '5O190127TN364715T';
		$_GET                  = [
			'token'   => '5O190127TN364715T',
			'PayerID' => 'PAYER123',
		];

		$this->sut->maybe_forward();

		$this->assertCount( 1, $this->redirects );
		$this->assertSame( self::RETURN_URL, strtok( $this->redirects[0], '?' ) );
		parse_str( (string) parse_url( $this->redirects[0], PHP_URL_QUERY ), $query );
		$this->assertSame( '5O190127TN364715T', $query['token'] );
		$this->assertSame( 'PAYER123', $query['PayerID'] );
	}

	/**
	 * GIVEN a stored order ID and a request
	 * WHEN the request has no token, a token for another order, or no PayerID
	 * THEN the buyer is not forwarded
	 *
	 * @dataProvider non_matching_request_provider
	 */
	public function test_does_not_forward_when_request_is_not_a_completed_return_for_stored_order(
		string $stored_id,
		array $query
	): void {
		$this->stored_order_id = $stored_id;
		$_GET                  = $query;

		$this->sut->maybe_forward();

		$this->assertSame( [], $this->redirects );
	}

	public function non_matching_request_provider(): array
	{
		return [
			'token belongs to another order' => [
				'5O190127TN364715T',
				[ 'token' => 'OTHERORDER', 'PayerID' => 'PAYER123' ],
			],
			'request has no token'           => [
				'5O190127TN364715T',
				[ 'PayerID' => 'PAYER123' ],
			],
			'request has an empty token'     => [
				'5O190127TN364715T',
				[ 'token' => '', 'PayerID' => 'PAYER123' ],
			],
			'matching token without PayerID' => [
				'5O190127TN364715T',
				[ 'token' => '5O190127TN364715T' ],
			],
			'matching token with empty PayerID' => [
				'5O190127TN364715T',
				[ 'token' => '5O190127TN364715T', 'PayerID' => '' ],
			],
			'no order ID is stored'          => [
				'',
				[ 'token' => 'OTHERORDER', 'PayerID' => 'PAYER123' ],
			],
			'no order ID stored, empty token' => [
				'',
				[ 'token' => '' ],
			],
		];
	}
}
