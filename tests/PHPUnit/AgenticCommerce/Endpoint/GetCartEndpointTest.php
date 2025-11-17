<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WooCommerce\PayPalCommerce\AgenticCommerce\Response\NewCartResponse;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WP_REST_Request;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\GetCartEndpoint
 */
class GetCartEndpointTest extends AgenticEndpointTestCase {

	public function test_get_cart_returns_200_ok_when_cart_exists(): void {
		$cart_id      = 't_mock_cart_id_12345';
		$ec_token     = 'EC-12345TOKEN';
		$created_time = time();

		$mock_cart_data = $this->cart()->with_item( 'TEST-001', 2, '50.00' )->to_array();

		$mocks            = $this->create_mocks();
		$session_handler  = $mocks['session_handler'];
		$response_factory = $mocks['response_factory'];

		$mock_cart = PayPalCart::from_array( $mock_cart_data );

		// Mock session handler.
		$session_handler->allows( 'load_cart_session' )
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $mock_cart,
					'ec_token' => $ec_token,
					'created'  => $created_time,
				)
			);

		// Mock response factory.
		$response_factory->allows( 'active_cart' )
			->andReturnUsing( fn( $cart, $cart_id, $ec_token ) => new NewCartResponse(
				$cart,
				$cart_id,
				$ec_token,
				'ACTIVE'
			) );

		$endpoint = new GetCartEndpoint( $mocks['auth_provider'], $session_handler, $response_factory );

		$request = new WP_REST_Request( 'GET', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );

		$response = $endpoint->get_cart( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_get_cart_returns_error_when_cart_unavailable(): void {
		$cart_id = 't_unavailable_cart_id';

		$mocks           = $this->create_mocks();
		$session_handler = $mocks['session_handler'];

		// Mock session handler - return null for unavailable cart (not found or expired).
		$session_handler->allows( 'load_cart_session' )
			->with( $cart_id )
			->andReturn( null );

		$endpoint = new GetCartEndpoint( $mocks['auth_provider'], $session_handler, $mocks['response_factory'] );

		$request = new WP_REST_Request( 'GET', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );

		$response = $endpoint->get_cart( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertNotSame( 200, $response->get_status() );
		$this->assert_error_response( $data );
	}
}
