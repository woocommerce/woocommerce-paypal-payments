<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\JwtAuthService;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\CartResponse;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
use WP_REST_Request;
use Mockery;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\ReplaceCartEndpoint
 */
class ReplaceCartEndpointTest extends TestCase {

	public function test_replace_cart_returns_200_ok_on_successful_replacement(): void {
		$cart_id      = 't_mock_cart_id_12345';
		$ec_token     = 'EC-12345TOKEN';
		$created_time = time();

		$existing_cart_data = array(
			'items'          => array(
				array(
					'item_id'  => 'OLD-ITEM-001',
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

		$replacement_cart_data = array(
			'items'          => array(
				array(
					'item_id'  => 'NEW-ITEM-002',
					'quantity' => 3,
					'price'    => array(
						'currency_code' => 'USD',
						'value'         => '50.00',
					),
				),
				array(
					'item_id'  => 'NEW-ITEM-003',
					'quantity' => 1,
					'price'    => array(
						'currency_code' => 'USD',
						'value'         => '30.00',
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

		$existing_cart = PayPalCart::from_array( $existing_cart_data );

		// First call - verify cart exists
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

		// Mock replacement operation
		$session_handler->shouldReceive( 'update_cart_session' )
			->once()
			->withArgs( function( $received_cart_id, $new_cart ) use ( $cart_id ) {
				return $received_cart_id === $cart_id && $new_cart instanceof PayPalCart;
			} )
			->andReturn( true );

		// Mock response factory - uses from_cart (not active_cart or new_cart)
		$replacement_cart = PayPalCart::from_array( $replacement_cart_data );
		$response_factory->shouldReceive( 'from_cart' )
			->once()
			->withArgs( function( $cart ) {
				return $cart instanceof PayPalCart;
			} )
			->andReturnUsing( fn( $cart ) => new CartResponse( $cart ) );

		$endpoint = new ReplaceCartEndpoint( $auth, $session_handler, $response_factory );

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $replacement_cart_data ) );

		$response = $endpoint->replace_cart( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 200, $response->get_status() );
	}

	public function test_replace_cart_returns_error_when_cart_not_found(): void {
		$cart_id = 't_nonexistent_cart_id';

		$replacement_data = array(
			'items'          => array(
				array(
					'item_id'  => 'TEST-001',
					'quantity' => 2,
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

		$endpoint = new ReplaceCartEndpoint( $auth, $session_handler, $response_factory );

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $replacement_data ) );

		$response = $endpoint->replace_cart( $request );
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

	public function test_replace_cart_completely_replaces_items(): void {
		$cart_id      = 't_mock_cart_id_12345';
		$ec_token     = 'EC-12345TOKEN';
		$created_time = time();

		// Original cart has 1 item
		$existing_cart_data = array(
			'items'          => array(
				array(
					'item_id'  => 'OLD-ITEM-001',
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
					'full_name' => 'Old Name',
				),
				'address' => array(
					'address_line_1' => '123 Old St',
					'admin_area_2'   => 'San Jose',
					'admin_area_1'   => 'CA',
					'postal_code'    => '95131',
					'country_code'   => 'US',
				),
			),
		);

		// Replacement cart has completely different items and shipping
		$replacement_cart_data = array(
			'items'          => array(
				array(
					'item_id'  => 'NEW-ITEM-002',
					'quantity' => 5,
					'price'    => array(
						'currency_code' => 'EUR',
						'value'         => '99.99',
					),
				),
			),
			'payment_method' => array(
				'type' => 'paypal',
			),
			'shipping'       => array(
				'name'    => array(
					'full_name' => 'New Name',
				),
				'address' => array(
					'address_line_1' => '456 New Ave',
					'admin_area_2'   => 'Berlin',
					'admin_area_1'   => 'BE',
					'postal_code'    => '10115',
					'country_code'   => 'DE',
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

		// Verify that update receives completely new data (full replacement)
		$session_handler->shouldReceive( 'update_cart_session' )
			->once()
			->withArgs( function( $received_cart_id, $new_cart ) use ( $cart_id ) {
				if ( $received_cart_id !== $cart_id || ! ( $new_cart instanceof PayPalCart ) ) {
					return false;
				}

				$cart_array = $new_cart->to_array();

				// Should have NEW item only (not old)
				if ( count( $cart_array['items'] ) !== 1 ) {
					return false;
				}

				if ( $cart_array['items'][0]['item_id'] !== 'NEW-ITEM-002' ) {
					return false;
				}

				// Should have NEW shipping info
				if ( $cart_array['shipping']['name']['full_name'] !== 'New Name' ) {
					return false;
				}

				if ( $cart_array['shipping']['address']['address_line_1'] !== '456 New Ave' ) {
					return false;
				}

				return true;
			} )
			->andReturn( true );

		// Mock response factory
		$replacement_cart = PayPalCart::from_array( $replacement_cart_data );
		$response_factory->shouldReceive( 'from_cart' )
			->once()
			->andReturnUsing( fn( $cart ) => new CartResponse( $cart ) );

		$endpoint = new ReplaceCartEndpoint( $auth, $session_handler, $response_factory );

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $replacement_cart_data ) );

		$response = $endpoint->replace_cart( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_replace_cart_returns_error_when_replacement_fails(): void {
		$cart_id      = 't_mock_cart_id_12345';
		$ec_token     = 'EC-12345TOKEN';
		$created_time = time();

		$existing_cart_data = array(
			'items'          => array(
				array(
					'item_id'  => 'OLD-ITEM-001',
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

		$replacement_data = array(
			'items'          => array(
				array(
					'item_id'  => 'NEW-ITEM-002',
					'quantity' => 3,
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

		// Mock replacement operation fails
		$session_handler->shouldReceive( 'update_cart_session' )
			->once()
			->andReturn( false );

		$endpoint = new ReplaceCartEndpoint( $auth, $session_handler, $response_factory );

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $replacement_data ) );

		$response = $endpoint->replace_cart( $request );
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
