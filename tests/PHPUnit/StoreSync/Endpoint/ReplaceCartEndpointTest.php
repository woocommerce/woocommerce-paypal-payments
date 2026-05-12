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

	/**
	 * GIVEN a cart session that has no ec_token (POST order creation had previously failed)
	 * WHEN a PUT /merchant-cart/{id} request is made with a valid, issue-free cart
	 * THEN create_order() is called to recover the missing PayPal order token
	 * AND the response is a 200 with the new token injected into the payment method data
	 */
	public function test_creates_paypal_order_when_session_has_no_token_and_cart_is_valid(): void {
		$cart_id      = 't_mock_cart_id_recover';
		$created_time = time();
		$new_token    = 'EC-NEWTOKEN-001';

		$cart_data = $this->cart()->with_item_fixture( 'new_item_2' )->to_array();

		$mocks           = $this->create_mocks();
		$session_handler = $mocks['session_handler'];
		$order_manager   = $mocks['order_manager'];

		$existing_cart = PayPalCart::from_array( $cart_data, new StoreValidation() );

		// Session has no ec_token — the previous POST failed to create a PayPal order.
		$session_handler->allows( 'load_cart_session' )
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $existing_cart,
					'ec_token' => '',
					'created'  => $created_time,
				)
			);

		$session_handler->allows( 'update_cart_session' )->andReturn( true );

		// The cart has no validation issues, so create_order MUST be called once.
		$order_manager->shouldReceive( 'create_order' )
			->once()
			->andReturn( $new_token );

		$mocks['validation_processor']->allows( 'validate_cart' )->andReturnUsing( fn( $c ) => $c );
		$mocks['response_factory']->allows( 'from_cart' )
			->andReturnUsing( fn( $cart, $id ) => \WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse::create( $cart, $id ) );

		// Build a StorePayPalCart mock that captures set_paypal_order.
		$store_cart_mock = Mockery::mock( \WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart::class );
		$store_cart_mock->allows( 'paypal_cart' )->andReturn( $existing_cart );
		$store_cart_mock->allows( 'validation' )->andReturn( new StoreValidation() );
		$store_cart_mock->allows( 'get_validation_issues' )->andReturn( array() );
		$store_cart_mock->allows( 'get_items' )->andReturn( array() );
		$store_cart_mock->allows( 'get_customer' )->andReturn( array() );
		$store_cart_mock->allows( 'get_shipping_address' )->andReturn( array() );
		$store_cart_mock->allows( 'get_billing_address' )->andReturn( null );
		$store_cart_mock->allows( 'get_totals' )->andReturn( null );
		$store_cart_mock->allows( 'get_payment_method' )->andReturn( array( 'type' => 'paypal', 'token' => $new_token ) );
		$store_cart_mock->shouldReceive( 'set_paypal_order' )->with( $new_token )->once();

		$store_data_mock = Mockery::mock( \WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreData::class );
		$store_data_mock->allows( 'create_cart' )->andReturn( $store_cart_mock );
		$mocks['store_data'] = $store_data_mock;

		$endpoint = new ReplaceCartEndpoint(
			$mocks['auth_provider'],
			$session_handler,
			$mocks['session_manager'],
			$mocks['response_factory'],
			$mocks['validation_processor'],
			$mocks['logger'],
			$order_manager,
			$store_data_mock
		);

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $cart_data ) );

		$response = $endpoint->replace_cart( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * GIVEN a cart session that already has a valid ec_token from a previous successful POST
	 * WHEN a PUT /merchant-cart/{id} request arrives
	 * THEN create_order() is NOT called (the existing token must be preserved)
	 * AND set_paypal_order() is NOT called on the cart (response must not mention existing tokens)
	 */
	public function test_does_not_create_paypal_order_when_session_already_has_token(): void {
		$cart_id        = 't_mock_cart_id_has_token';
		$existing_token = 'EC-EXISTING-TOKEN';
		$created_time   = time();

		$cart_data = $this->cart()->with_item_fixture( 'new_item_2' )->to_array();

		$mocks           = $this->create_mocks();
		$session_handler = $mocks['session_handler'];
		$order_manager   = $mocks['order_manager'];

		$existing_cart = PayPalCart::from_array( $cart_data, new StoreValidation() );

		// Session already holds a valid token.
		$session_handler->allows( 'load_cart_session' )
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $existing_cart,
					'ec_token' => $existing_token,
					'created'  => $created_time,
				)
			);

		$session_handler->allows( 'update_cart_session' )->andReturn( true );

		// create_order must never be called when the session already has a token.
		$order_manager->shouldNotReceive( 'create_order' );

		$mocks['validation_processor']->allows( 'validate_cart' )->andReturnUsing( fn( $c ) => $c );
		$mocks['response_factory']->allows( 'from_cart' )
			->andReturnUsing( fn( $cart, $id ) => \WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse::create( $cart, $id ) );

		$store_cart_mock = Mockery::mock( \WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart::class );
		$store_cart_mock->allows( 'paypal_cart' )->andReturn( $existing_cart );
		$store_cart_mock->allows( 'validation' )->andReturn( new StoreValidation() );
		$store_cart_mock->allows( 'get_validation_issues' )->andReturn( array() );
		$store_cart_mock->allows( 'get_items' )->andReturn( array() );
		$store_cart_mock->allows( 'get_customer' )->andReturn( array() );
		$store_cart_mock->allows( 'get_shipping_address' )->andReturn( array() );
		$store_cart_mock->allows( 'get_billing_address' )->andReturn( null );
		$store_cart_mock->allows( 'get_totals' )->andReturn( null );
		$store_cart_mock->allows( 'get_payment_method' )->andReturn( array( 'type' => 'paypal' ) );
		// set_paypal_order must not be called — response must not mention the existing token.
		$store_cart_mock->shouldNotReceive( 'set_paypal_order' );

		$store_data_mock = Mockery::mock( \WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreData::class );
		$store_data_mock->allows( 'create_cart' )->andReturn( $store_cart_mock );

		$endpoint = new ReplaceCartEndpoint(
			$mocks['auth_provider'],
			$session_handler,
			$mocks['session_manager'],
			$mocks['response_factory'],
			$mocks['validation_processor'],
			$mocks['logger'],
			$order_manager,
			$store_data_mock
		);

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $cart_data ) );

		$response = $endpoint->replace_cart( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * GIVEN a cart session with no ec_token
	 * WHEN a PUT request arrives but the cart has validation issues (e.g. out-of-stock item)
	 * THEN create_order() is NOT called (a broken cart must not create a PayPal order)
	 * AND the response is still 200 (validation issues are surfaced in the payload, not as HTTP errors)
	 */
	public function test_does_not_create_paypal_order_when_cart_has_validation_issues(): void {
		$cart_id      = 't_mock_cart_id_invalid';
		$created_time = time();

		$cart_data = $this->cart()->with_item_fixture( 'new_item_2' )->to_array();

		$mocks           = $this->create_mocks();
		$session_handler = $mocks['session_handler'];
		$order_manager   = $mocks['order_manager'];

		$existing_cart = PayPalCart::from_array( $cart_data, new StoreValidation() );

		// Session has no ec_token.
		$session_handler->allows( 'load_cart_session' )
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $existing_cart,
					'ec_token' => '',
					'created'  => $created_time,
				)
			);

		$session_handler->allows( 'update_cart_session' )->andReturn( true );

		// Cart has a validation issue — create_order must never be called.
		$order_manager->shouldNotReceive( 'create_order' );

		$mocks['validation_processor']->allows( 'validate_cart' )->andReturnUsing( fn( $c ) => $c );
		$mocks['response_factory']->allows( 'from_cart' )
			->andReturnUsing( fn( $cart, $id ) => \WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse::create( $cart, $id ) );

		// Build a validation with at least one issue.
		$validation_with_issue = new StoreValidation();
		$validation_with_issue->add_invalid_product( 'Item is out of stock' );

		$store_cart_mock = Mockery::mock( \WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart::class );
		$store_cart_mock->allows( 'paypal_cart' )->andReturn( $existing_cart );
		$store_cart_mock->allows( 'validation' )->andReturn( $validation_with_issue );
		$store_cart_mock->allows( 'get_validation_issues' )->andReturn( $validation_with_issue->all() );
		$store_cart_mock->allows( 'get_items' )->andReturn( array() );
		$store_cart_mock->allows( 'get_customer' )->andReturn( array() );
		$store_cart_mock->allows( 'get_shipping_address' )->andReturn( array() );
		$store_cart_mock->allows( 'get_billing_address' )->andReturn( null );
		$store_cart_mock->allows( 'get_totals' )->andReturn( null );
		$store_cart_mock->allows( 'get_payment_method' )->andReturn( array( 'type' => 'paypal' ) );
		$store_cart_mock->shouldNotReceive( 'set_paypal_order' );

		$store_data_mock = Mockery::mock( \WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreData::class );
		$store_data_mock->allows( 'create_cart' )->andReturn( $store_cart_mock );

		$endpoint = new ReplaceCartEndpoint(
			$mocks['auth_provider'],
			$session_handler,
			$mocks['session_manager'],
			$mocks['response_factory'],
			$mocks['validation_processor'],
			$mocks['logger'],
			$order_manager,
			$store_data_mock
		);

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $cart_data ) );

		$response = $endpoint->replace_cart( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * GIVEN a cart session with no ec_token and a valid cart
	 * WHEN create_order() succeeds and returns a new token
	 * THEN update_cart_session() is called with that new token as the third argument
	 * AND the new token is persisted in the session for future requests
	 */
	public function test_new_token_is_stored_in_session_when_created(): void {
		$cart_id      = 't_mock_cart_id_persist';
		$created_time = time();
		$new_token    = 'EC-STORED-TOKEN-999';

		$cart_data = $this->cart()->with_item_fixture( 'new_item_2' )->to_array();

		$mocks           = $this->create_mocks();
		$session_handler = $mocks['session_handler'];
		$order_manager   = $mocks['order_manager'];

		$existing_cart = PayPalCart::from_array( $cart_data, new StoreValidation() );

		$session_handler->allows( 'load_cart_session' )
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $existing_cart,
					'ec_token' => '',
					'created'  => $created_time,
				)
			);

		// The new token must be forwarded to update_cart_session as the third argument.
		$session_handler->shouldReceive( 'update_cart_session' )
			->once()
			->withArgs(
				function ( $received_id, $received_cart, $received_token ) use ( $cart_id, $new_token ) {
					return $received_id === $cart_id
						&& $received_cart instanceof PayPalCart
						&& $received_token === $new_token;
				}
			)
			->andReturn( true );

		$order_manager->allows( 'create_order' )->andReturn( $new_token );

		$mocks['validation_processor']->allows( 'validate_cart' )->andReturnUsing( fn( $c ) => $c );
		$mocks['response_factory']->allows( 'from_cart' )
			->andReturnUsing( fn( $cart, $id ) => \WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse::create( $cart, $id ) );

		$store_cart_mock = Mockery::mock( \WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart::class );
		$store_cart_mock->allows( 'paypal_cart' )->andReturn( $existing_cart );
		$store_cart_mock->allows( 'validation' )->andReturn( new StoreValidation() );
		$store_cart_mock->allows( 'get_validation_issues' )->andReturn( array() );
		$store_cart_mock->allows( 'get_items' )->andReturn( array() );
		$store_cart_mock->allows( 'get_customer' )->andReturn( array() );
		$store_cart_mock->allows( 'get_shipping_address' )->andReturn( array() );
		$store_cart_mock->allows( 'get_billing_address' )->andReturn( null );
		$store_cart_mock->allows( 'get_totals' )->andReturn( null );
		$store_cart_mock->allows( 'get_payment_method' )->andReturn( array( 'type' => 'paypal', 'token' => $new_token ) );
		$store_cart_mock->allows( 'set_paypal_order' );

		$store_data_mock = Mockery::mock( \WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreData::class );
		$store_data_mock->allows( 'create_cart' )->andReturn( $store_cart_mock );

		$endpoint = new ReplaceCartEndpoint(
			$mocks['auth_provider'],
			$session_handler,
			$mocks['session_manager'],
			$mocks['response_factory'],
			$mocks['validation_processor'],
			$mocks['logger'],
			$order_manager,
			$store_data_mock
		);

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $cart_data ) );

		$response = $endpoint->replace_cart( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * GIVEN a cart session with no ec_token and a valid cart
	 * WHEN create_order() fails and returns an empty/falsy value
	 * THEN set_paypal_order() is NOT called on the cart (no broken token in response)
	 * AND the session update proceeds without a token (null passed as third argument)
	 * AND the response is still 200
	 */
	public function test_no_token_set_on_cart_when_order_creation_fails(): void {
		$cart_id      = 't_mock_cart_id_fail_order';
		$created_time = time();

		$cart_data = $this->cart()->with_item_fixture( 'new_item_2' )->to_array();

		$mocks           = $this->create_mocks();
		$session_handler = $mocks['session_handler'];
		$order_manager   = $mocks['order_manager'];

		$existing_cart = PayPalCart::from_array( $cart_data, new StoreValidation() );

		$session_handler->allows( 'load_cart_session' )
			->with( $cart_id )
			->andReturn(
				array(
					'cart'     => $existing_cart,
					'ec_token' => '',
					'created'  => $created_time,
				)
			);

		// update_cart_session must receive null as token when order creation failed.
		$session_handler->shouldReceive( 'update_cart_session' )
			->once()
			->withArgs(
				function ( $received_id, $received_cart, $received_token ) use ( $cart_id ) {
					return $received_id === $cart_id
						&& $received_cart instanceof PayPalCart
						&& $received_token === null;
				}
			)
			->andReturn( true );

		// create_order returns falsy — simulates API failure.
		$order_manager->allows( 'create_order' )->andReturn( '' );

		$mocks['validation_processor']->allows( 'validate_cart' )->andReturnUsing( fn( $c ) => $c );
		$mocks['response_factory']->allows( 'from_cart' )
			->andReturnUsing( fn( $cart, $id ) => \WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse::create( $cart, $id ) );

		$store_cart_mock = Mockery::mock( \WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart::class );
		$store_cart_mock->allows( 'paypal_cart' )->andReturn( $existing_cart );
		$store_cart_mock->allows( 'validation' )->andReturn( new StoreValidation() );
		$store_cart_mock->allows( 'get_validation_issues' )->andReturn( array() );
		$store_cart_mock->allows( 'get_items' )->andReturn( array() );
		$store_cart_mock->allows( 'get_customer' )->andReturn( array() );
		$store_cart_mock->allows( 'get_shipping_address' )->andReturn( array() );
		$store_cart_mock->allows( 'get_billing_address' )->andReturn( null );
		$store_cart_mock->allows( 'get_totals' )->andReturn( null );
		$store_cart_mock->allows( 'get_payment_method' )->andReturn( array( 'type' => 'paypal' ) );
		// No token must be injected into the cart response when order creation failed.
		$store_cart_mock->shouldNotReceive( 'set_paypal_order' );

		$store_data_mock = Mockery::mock( \WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreData::class );
		$store_data_mock->allows( 'create_cart' )->andReturn( $store_cart_mock );

		$endpoint = new ReplaceCartEndpoint(
			$mocks['auth_provider'],
			$session_handler,
			$mocks['session_manager'],
			$mocks['response_factory'],
			$mocks['validation_processor'],
			$mocks['logger'],
			$order_manager,
			$store_data_mock
		);

		$request = new WP_REST_Request( 'PUT', "/wp-json/paypal/v1/merchant-cart/{$cart_id}" );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( json_encode( $cart_data ) );

		$response = $endpoint->replace_cart( $request );

		$this->assertSame( 200, $response->get_status() );
	}
}
