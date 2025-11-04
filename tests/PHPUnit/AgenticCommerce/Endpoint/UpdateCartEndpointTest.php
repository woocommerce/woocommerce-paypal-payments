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
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\UpdateCartEndpoint
 */
class UpdateCartEndpointTest extends TestCase {

	public function test_update_cart_returns_200_ok_on_successful_update(): void {
		$cart_id      = 't_mock_cart_id_12345';
		$ec_token     = 'EC-12345TOKEN';
		$created_time = time();

		$existing_cart_data = array(
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

		$update_data = array(
			'items' => array(
				array(
					'item_id'  => 'TEST-001',
					'quantity' => 3, // Updated quantity
					'price'    => array(
						'currency_code' => 'USD',
						'value'         => '25.00',
					),
				),
			),
		);

		/** @var JwtAuthService&\Mockery\MockInterface $auth */
		$auth = Mockery::mock( JwtAuthService::class );
		/** @var AgenticSessionHandler&\Mockery\MockInterface $session_handler */
		$session_handler = Mockery::mock( AgenticSessionHandler::class );
		/** @var ResponseFactory&\Mockery\MockInterface $response_factory */
		$response_factory = Mockery::mock( ResponseFactory::class );

		$existing_cart = PayPalCart::from_array( $existing_cart_data );

		// First call - load existing cart
		$session_handler->shouldReceive( 'load_cart_session' )
			->once()
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $existing_cart,
					'ec_token' => $ec_token,
					'created'  => $created_time,
				)
			);

		// Mock update operation
		$session_handler->shouldReceive( 'update_cart_session' )
			->once()
			->withArgs( function( $received_cart_id, $updated_cart ) use ( $cart_id ) {
				return $received_cart_id === $cart_id && $updated_cart instanceof PayPalCart;
			} )
			->andReturn( true );

		// Second call - reload updated cart
		$updated_cart = PayPalCart::from_array( array_merge( $existing_cart_data, $update_data ) );
		$session_handler->shouldReceive( 'load_cart_session' )
			->once()
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $updated_cart,
					'ec_token' => $ec_token,
					'created'  => $created_time,
					'modified' => time(),
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

		$endpoint = new UpdateCartEndpoint( $auth, $session_handler, $response_factory );

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $update_data ) );

		$response = $endpoint->update_cart( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_update_cart_returns_error_when_cart_not_found(): void {
		$cart_id = 't_nonexistent_cart_id';

		$update_data = array(
			'items' => array(
				array(
					'item_id'  => 'TEST-001',
					'quantity' => 2,
					'price'    => array(
						'currency_code' => 'USD',
						'value'         => '25.00',
					),
				),
			),
		);

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

		$endpoint = new UpdateCartEndpoint( $auth, $session_handler, $response_factory );

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $update_data ) );

		$response = $endpoint->update_cart( $request );
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

	public function test_update_cart_returns_error_when_update_fails(): void {
		$cart_id      = 't_mock_cart_id_12345';
		$ec_token     = 'EC-12345TOKEN';
		$created_time = time();

		$existing_cart_data = array(
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

		$update_data = array(
			'items' => array(
				array(
					'item_id'  => 'TEST-001',
					'quantity' => 3,
					'price'    => array(
						'currency_code' => 'USD',
						'value'         => '25.00',
					),
				),
			),
		);

		/** @var JwtAuthService&\Mockery\MockInterface $auth */
		$auth = Mockery::mock( JwtAuthService::class );
		/** @var AgenticSessionHandler&\Mockery\MockInterface $session_handler */
		$session_handler = Mockery::mock( AgenticSessionHandler::class );
		/** @var ResponseFactory&\Mockery\MockInterface $response_factory */
		$response_factory = Mockery::mock( ResponseFactory::class );

		$existing_cart = PayPalCart::from_array( $existing_cart_data );

		// Mock load existing cart
		$session_handler->shouldReceive( 'load_cart_session' )
			->once()
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $existing_cart,
					'ec_token' => $ec_token,
					'created'  => $created_time,
				)
			);

		// Mock update operation - return false to simulate failure
		$session_handler->shouldReceive( 'update_cart_session' )
			->once()
			->withArgs( function( $received_cart_id, $updated_cart ) use ( $cart_id ) {
				return $received_cart_id === $cart_id && $updated_cart instanceof PayPalCart;
			} )
			->andReturn( false );

		$endpoint = new UpdateCartEndpoint( $auth, $session_handler, $response_factory );

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $update_data ) );

		$response = $endpoint->update_cart( $request );
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

	public function test_update_cart_returns_error_when_verification_fails(): void {
		$cart_id      = 't_mock_cart_id_12345';
		$ec_token     = 'EC-12345TOKEN';
		$created_time = time();

		$existing_cart_data = array(
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

		$update_data = array(
			'items' => array(
				array(
					'item_id'  => 'TEST-001',
					'quantity' => 3,
					'price'    => array(
						'currency_code' => 'USD',
						'value'         => '25.00',
					),
				),
			),
		);

		/** @var JwtAuthService&\Mockery\MockInterface $auth */
		$auth = Mockery::mock( JwtAuthService::class );
		/** @var AgenticSessionHandler&\Mockery\MockInterface $session_handler */
		$session_handler = Mockery::mock( AgenticSessionHandler::class );
		/** @var ResponseFactory&\Mockery\MockInterface $response_factory */
		$response_factory = Mockery::mock( ResponseFactory::class );

		$existing_cart = PayPalCart::from_array( $existing_cart_data );

		// First load - returns existing cart
		$session_handler->shouldReceive( 'load_cart_session' )
			->once()
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $existing_cart,
					'ec_token' => $ec_token,
					'created'  => $created_time,
				)
			);

		// Mock update operation succeeds
		$session_handler->shouldReceive( 'update_cart_session' )
			->once()
			->withArgs( function( $received_cart_id, $updated_cart ) use ( $cart_id ) {
				return $received_cart_id === $cart_id && $updated_cart instanceof PayPalCart;
			} )
			->andReturn( true );

		// Second load - return null to simulate verification failure
		$session_handler->shouldReceive( 'load_cart_session' )
			->once()
			->with( $cart_id )
			->andReturn( null );

		$endpoint = new UpdateCartEndpoint( $auth, $session_handler, $response_factory );

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $update_data ) );

		$response = $endpoint->update_cart( $request );
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

	public function test_update_cart_performs_partial_update(): void {
		$cart_id      = 't_mock_cart_id_12345';
		$ec_token     = 'EC-12345TOKEN';
		$created_time = time();

		// Existing cart has multiple fields
		$existing_cart_data = array(
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
			'shipping'       => array(
				'name'    => array(
					'full_name' => 'John Doe',
				),
				'address' => array(
					'address_line_1' => '123 Main St',
					'admin_area_2'   => 'San Jose',
					'admin_area_1'   => 'CA',
					'postal_code'    => '95131',
					'country_code'   => 'US',
				),
			),
		);

		// Update only modifies items, should preserve shipping
		$update_data = array(
			'items' => array(
				array(
					'item_id'  => 'TEST-001',
					'quantity' => 5,
					'price'    => array(
						'currency_code' => 'USD',
						'value'         => '25.00',
					),
				),
			),
		);

		/** @var JwtAuthService&\Mockery\MockInterface $auth */
		$auth = Mockery::mock( JwtAuthService::class );
		/** @var AgenticSessionHandler&\Mockery\MockInterface $session_handler */
		$session_handler = Mockery::mock( AgenticSessionHandler::class );
		/** @var ResponseFactory&\Mockery\MockInterface $response_factory */
		$response_factory = Mockery::mock( ResponseFactory::class );

		$existing_cart = PayPalCart::from_array( $existing_cart_data );

		// Mock load existing cart
		$session_handler->shouldReceive( 'load_cart_session' )
			->once()
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $existing_cart,
					'ec_token' => $ec_token,
					'created'  => $created_time,
				)
			);

		// Verify that update receives merged data (partial update)
		$session_handler->shouldReceive( 'update_cart_session' )
			->once()
			->withArgs( function( $received_cart_id, $updated_cart ) use ( $cart_id ) {
				if ( $received_cart_id !== $cart_id || ! ( $updated_cart instanceof PayPalCart ) ) {
					return false;
				}

				$cart_array = $updated_cart->to_array();

				// Should have updated items
				if ( $cart_array['items'][0]['quantity'] !== 5 ) {
					return false;
				}

				// Should preserve shipping info
				if ( ! isset( $cart_array['shipping'] ) ) {
					return false;
				}

				return true;
			} )
			->andReturn( true );

		// Mock reload after update
		$merged_cart_data = array_merge( $existing_cart_data, $update_data );
		$updated_cart     = PayPalCart::from_array( $merged_cart_data );
		$session_handler->shouldReceive( 'load_cart_session' )
			->once()
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $updated_cart,
					'ec_token' => $ec_token,
					'created'  => $created_time,
					'modified' => time(),
				)
			);

		// Mock response factory
		$response_factory->shouldReceive( 'active_cart' )
			->once()
			->andReturnUsing( fn( $cart, $cart_id, $ec_token ) => new NewCartResponse(
				$cart,
				$cart_id,
				$ec_token,
				'ACTIVE'
			) );

		$endpoint = new UpdateCartEndpoint( $auth, $session_handler, $response_factory );

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $update_data ) );

		$response = $endpoint->update_cart( $request );

		$this->assertSame( 200, $response->get_status() );
	}
}
