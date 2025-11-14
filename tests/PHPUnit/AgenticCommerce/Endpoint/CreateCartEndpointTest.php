<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\JwtAuthService;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\NewCartResponse;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
use WP_REST_Request;
use Mockery;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\CreateCartEndpoint
 */
class CreateCartEndpointTest extends TestCase {

	public function test_create_cart_returns_201_created(): void {
		$sample_token = 'random-string';
		$cart_id      = 't_mock_cart_id_12345';
		$cart_data    = array(
			'items'          => array(
				array(
					'item_id'  => 'TEST-001',
					'quantity' => 1,
					'price'    => array(
						'currency_code' => 'USD',
						'value'         => '25.00',
					),
				),
			),
			'payment_method' => array(
				'type' => 'paypal',
			),
		);

		when( 'wp_generate_password' )->justReturn( $sample_token );

		/** @var JwtAuthService&\Mockery\MockInterface $auth_service */
		$auth_service = Mockery::mock( JwtAuthService::class );
		/** @var AuthServiceProvider&\Mockery\MockInterface $auth_provider */
		$auth_provider = Mockery::mock( AuthServiceProvider::class );
		/** @var AgenticSessionHandler&\Mockery\MockInterface $session_handler */
		$session_handler = Mockery::mock( AgenticSessionHandler::class );
		/** @var ResponseFactory&\Mockery\MockInterface $response_factory */
		$response_factory = Mockery::mock( ResponseFactory::class );

		// Mock auth provider to return auth service.
		$auth_provider->allows( 'auth_service' )->andReturn( $auth_service );

		// Mock session handler - it should create a cart session and return the cart ID.
		$session_handler->shouldReceive( 'create_cart_session' )
			->once()
			->withArgs( function( $cart, $ec_token ) use ( $sample_token ) {
				return $cart instanceof PayPalCart && $ec_token === $sample_token;
			} )
			->andReturn( $cart_id );

		// Mock response factory - now expects 3 arguments: cart, cart_id, ec_token.
		$response_factory->shouldReceive( 'new_cart' )
			->once()
			->withArgs( function( $cart, $received_cart_id, $ec_token ) use ( $cart_id, $sample_token ) {
				return $cart instanceof PayPalCart
					&& $received_cart_id === $cart_id
					&& $ec_token === $sample_token;
			} )
			->andReturnUsing( fn( $cart, $cart_id, $ec_token ) => new NewCartResponse(
				$cart,
				$cart_id,
				$ec_token
			) );

		// Pass auth_provider as 1st argument.
		$endpoint = new CreateCartEndpoint( $auth_provider, $session_handler, $response_factory );

		$request = new WP_REST_Request( 'POST', '/wp-json/paypal/v1/merchant-cart' );
		$request->set_body( json_encode( $cart_data ) );

		$response = $endpoint->create_cart( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data['validation_issues'] );
		$this->assertEmpty( $data['validation_issues'] );
		$this->assertSame( 'CREATED', $data['status'] );
		$this->assertSame( 'VALID', $data['validation_status'] );

		$this->assertSame( 201, $response->get_status() );
	}
}
