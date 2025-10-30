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
use function Brain\Monkey\Functions\when;

/**
 * @covers CreateCartEndpoint
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

		$auth             = Mockery::mock( JwtAuthService::class );
		$session_handler  = Mockery::mock( AgenticSessionHandler::class );
		$response_factory = Mockery::mock( ResponseFactory::class );

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

		// Pass session_handler as 2nd argument
		$endpoint = new CreateCartEndpoint( $auth, $session_handler, $response_factory );

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
