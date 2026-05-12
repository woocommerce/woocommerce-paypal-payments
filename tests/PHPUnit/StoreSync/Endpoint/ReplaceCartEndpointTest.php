<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Endpoint;

use Mockery;
use WP_REST_Request;

use WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Endpoint\ReplaceCartEndpoint
 */
class ReplaceCartEndpointTest extends AgenticEndpointTestCase {

	public function test_replace_cart_returns_200_ok_on_successful_replacement(): void {
		$cart_id      = 't_mock_cart_id_12345';
		$ec_token     = 'EC-12345TOKEN';
		$created_time = time();

		$existing_cart_data = $this->cart()
			->with_item_fixture( 'old_item' )
			->to_array();

		$replacement_cart_data = $this->cart()
			->with_item_fixture( 'new_item_2' )
			->with_item_fixture( 'new_item_3' )
			->to_array();

		$mocks            = $this->create_mocks();
		$session_handler  = $mocks['session_handler'];
		$response_factory = $mocks['response_factory'];

		$existing_cart = PayPalCart::from_array( $existing_cart_data, new StoreValidation() );

		// Mock session handler.
		$session_handler->allows( 'load_cart_session' )
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $existing_cart,
					'ec_token' => $ec_token,
					'created'  => $created_time,
				)
			);

		$session_handler->allows( 'update_cart_session' )
			->andReturn( true );

		// Mock response factory.
		$response_factory->allows( 'from_cart' )
			->andReturnUsing( fn( $cart ) => CartResponse::create( $cart, '' ) );

		// Mock order_manager behavior
		$order_manager = $mocks['order_manager'];
		$order_manager->allows( 'update_order' )->andReturn( true );

		// Mock validation_processor to return valid cart
		$validation_processor = $mocks['validation_processor'];
		$validation_processor->allows( 'validate_cart' )->andReturnUsing( fn( $cart ) => $cart );

		$endpoint = new ReplaceCartEndpoint(
			$mocks['auth_provider'],
			$session_handler,
			$mocks['session_manager'],
			$response_factory,
			$validation_processor,
			$mocks['logger'],
			$order_manager,
			$mocks['store_data']
		);

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

		$replacement_data = $this->cart()
			->with_item( 'TEST-001', 2 )
			->to_array();

		$mocks           = $this->create_mocks();
		$session_handler = $mocks['session_handler'];

		// Mock session handler - return null for non-existent cart.
		$session_handler->allows( 'load_cart_session' )
			->with( $cart_id )
			->andReturn( null );

		$endpoint = new ReplaceCartEndpoint(
			$mocks['auth_provider'],
			$session_handler,
			$mocks['session_manager'],
			$mocks['response_factory'],
			$mocks['validation_processor'],
			$mocks['logger'],
			$mocks['order_manager'],
			$mocks['store_data']
		);

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $replacement_data ) );

		$response = $endpoint->replace_cart( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertNotSame( 200, $response->get_status() );
		$this->assert_error_response( $data );
	}

	public function test_replace_cart_completely_replaces_items(): void {
		$cart_id      = 't_mock_cart_id_12345';
		$ec_token     = 'EC-12345TOKEN';
		$created_time = time();

		$existing_cart_data = $this->cart()
			->with_item_fixture( 'old_item' )
			->with_shipping_fixture( 'us_old' )
			->to_array();

		$replacement_cart_data = $this->cart()
			->with_item_fixture( 'eur_item' )
			->with_shipping_fixture( 'de_new' )
			->to_array();

		$mocks            = $this->create_mocks();
		$session_handler  = $mocks['session_handler'];
		$response_factory = $mocks['response_factory'];

		$existing_cart = PayPalCart::from_array( $existing_cart_data, new StoreValidation() );

		// Mock load existing cart.
		$session_handler->allows( 'load_cart_session' )
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $existing_cart,
					'ec_token' => $ec_token,
					'created'  => $created_time,
				)
			);

		// Verify that update receives completely new data (full replacement).
		$session_handler->shouldReceive( 'update_cart_session' )
			->once()
			->withArgs( function ( $received_cart_id, $new_cart ) use ( $cart_id ) {
				if ( $received_cart_id !== $cart_id || ! ( $new_cart instanceof PayPalCart ) ) {
					return false;
				}

				$cart_array = $new_cart->to_array();

				// Should have NEW item only (not old).
				if ( count( $cart_array['items'] ) !== 1 ) {
					return false;
				}

				if ( $cart_array['items'][0]['item_id'] !== 'NEW-ITEM-002' ) {
					return false;
				}

				// Should have NEW shipping info.
				if ( ( $cart_array['shipping_address']['address_line_1'] ?? '' ) !== '456 New Ave' ) {
					return false;
				}

				return true;
			} )
			->andReturn( true );

		// Mock response factory.
		$response_factory->allows( 'from_cart' )
			->andReturnUsing( fn( $cart ) => CartResponse::create( $cart, '' ) );

		// Mock order_manager behavior
		$order_manager = $mocks['order_manager'];
		$order_manager->allows( 'update_order' )->andReturn( true );

		// Mock validation_processor to return valid cart
		$validation_processor = $mocks['validation_processor'];
		$validation_processor->allows( 'validate_cart' )->andReturnUsing( fn( $cart ) => $cart );

		$endpoint = new ReplaceCartEndpoint(
			$mocks['auth_provider'],
			$session_handler,
			$mocks['session_manager'],
			$response_factory,
			$validation_processor,
			$mocks['logger'],
			$order_manager,
			$mocks['store_data']
		);

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

		$existing_cart_data = $this->cart()
			->with_item_fixture( 'old_item' )
			->to_array();

		$replacement_data = $this->cart()
			->with_item_fixture( 'new_item_2' )
			->to_array();

		$mocks            = $this->create_mocks();
		$session_handler  = $mocks['session_handler'];
		$response_factory = $mocks['response_factory'];

		$existing_cart = PayPalCart::from_array( $existing_cart_data, new StoreValidation() );

		// Mock load existing cart.
		$session_handler->allows( 'load_cart_session' )
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $existing_cart,
					'ec_token' => $ec_token,
					'created'  => $created_time,
				)
			);

		// Mock replacement operation fails.
		$session_handler->allows( 'update_cart_session' )
			->andReturn( false );

		// Mock order_manager behavior - should succeed since we're testing session update failure
		$order_manager = $mocks['order_manager'];
		$order_manager->allows( 'update_order' )->andReturn( true );

		// Mock validation_processor to return valid cart
		$validation_processor = $mocks['validation_processor'];
		$validation_processor->allows( 'validate_cart' )->andReturnUsing( fn( $cart ) => $cart );

		$endpoint = new ReplaceCartEndpoint(
			$mocks['auth_provider'],
			$session_handler,
			$mocks['session_manager'],
			$response_factory,
			$validation_processor,
			$mocks['logger'],
			$order_manager,
			$mocks['store_data']
		);

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $replacement_data ) );

		$response = $endpoint->replace_cart( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertNotSame( 200, $response->get_status() );
		$this->assert_error_response( $data );
	}
}
