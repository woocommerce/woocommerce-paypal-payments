<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\JwtAuthService;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\NewCartResponse;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
use WP_REST_Request;
use Mockery;

/**
 * @covers GetCartEndpoint
 */
class GetCartEndpointTest extends TestCase {

	public function test_get_cart_returns_200_ok_when_cart_exists(): void {
		$cart_id      = 't_mock_cart_id_12345';
		$ec_token     = 'EC-12345TOKEN';
		$created_time = time();

		$mock_cart_data = array(
			'items'          => array(
				array(
					'item_id'  => 'TEST-001',
					'quantity' => 2,
					'price'    => array(
						'currency_code' => 'USD',
						'value'         => '50.00',
					),
				),
			),
			'payment_method' => array(
				'type' => 'paypal',
			),
		);

		/** @var JwtAuthService&\Mockery\MockInterface $auth */
		$auth = Mockery::mock( JwtAuthService::class );
		/** @var AgenticSessionHandler&\Mockery\MockInterface $session_handler */
		$session_handler = Mockery::mock( AgenticSessionHandler::class );
		/** @var ResponseFactory&\Mockery\MockInterface $response_factory */
		$response_factory = Mockery::mock( ResponseFactory::class );

		// Mock cart object
		$mock_cart = PayPalCart::from_array( $mock_cart_data );

		// Mock session handler - it should load the cart session
		$session_handler->shouldReceive( 'load_cart_session' )
			->once()
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $mock_cart,
					'ec_token' => $ec_token,
					'created'  => $created_time,
				)
			);

		// Mock response factory
		$response_factory->shouldReceive( 'active_cart' )
			->once()
			->withArgs( function( $cart, $received_cart_id, $received_ec_token ) use ( $cart_id, $ec_token ) {
				return $cart instanceof PayPalCart
					&& $received_cart_id === $cart_id
					&& $received_ec_token === $ec_token;
			} )
			->andReturnUsing( fn( $cart, $cart_id, $ec_token ) => new NewCartResponse(
				$cart,
				$cart_id,
				$ec_token,
				'ACTIVE'
			) );

		$endpoint = new GetCartEndpoint( $auth, $session_handler, $response_factory );

		$request = new WP_REST_Request( 'GET', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );

		$response = $endpoint->get_cart( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_get_cart_returns_error_when_cart_not_found(): void {
		$cart_id = 't_nonexistent_cart_id';

		/** @var JwtAuthService&\Mockery\MockInterface $auth */
		$auth = Mockery::mock( JwtAuthService::class );
		/** @var AgenticSessionHandler&\Mockery\MockInterface $session_handler */
		$session_handler = Mockery::mock( AgenticSessionHandler::class );
		/** @var ResponseFactory&\Mockery\MockInterface $response_factory */
		$response_factory = Mockery::mock( ResponseFactory::class );

		// Mock session handler - return null for non-existent cart
		$session_handler->shouldReceive( 'load_cart_session' )
			->once()
			->with( $cart_id )
			->andReturn( null );

		$endpoint = new GetCartEndpoint( $auth, $session_handler, $response_factory );

		$request = new WP_REST_Request( 'GET', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );

		$response = $endpoint->get_cart( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );

		// Error responses should not have 200 status
		$this->assertNotSame( 200, $response->get_status() );

		// The response should have error information - check for any of these keys
		$has_error_info = isset( $data['validation_issues'] )
			|| isset( $data['error'] )
			|| isset( $data['message'] )
			|| isset( $data['issues'] );

		$this->assertTrue( $has_error_info, 'Response should contain error information. Got: ' . json_encode( $data ) );
	}

	public function test_get_cart_returns_error_when_cart_expired(): void {
		$cart_id = 't_expired_cart_id';

		/** @var JwtAuthService&\Mockery\MockInterface $auth */
		$auth = Mockery::mock( JwtAuthService::class );
		/** @var AgenticSessionHandler&\Mockery\MockInterface $session_handler */
		$session_handler = Mockery::mock( AgenticSessionHandler::class );
		/** @var ResponseFactory&\Mockery\MockInterface $response_factory */
		$response_factory = Mockery::mock( ResponseFactory::class );

		// Mock session handler - return null for expired cart
		$session_handler->shouldReceive( 'load_cart_session' )
			->once()
			->with( $cart_id )
			->andReturn( null );

		$endpoint = new GetCartEndpoint( $auth, $session_handler, $response_factory );

		$request = new WP_REST_Request( 'GET', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );

		$response = $endpoint->get_cart( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );

		// Error responses should not have 200 status
		$this->assertNotSame( 200, $response->get_status() );

		// The response should have error information
		$has_error_info = isset( $data['validation_issues'] )
			|| isset( $data['error'] )
			|| isset( $data['message'] )
			|| isset( $data['issues'] );

		$this->assertTrue( $has_error_info, 'Response should contain error information. Got: ' . json_encode( $data ) );
	}
}
