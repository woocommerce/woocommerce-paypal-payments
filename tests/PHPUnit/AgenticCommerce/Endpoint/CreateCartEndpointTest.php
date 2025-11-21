<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WooCommerce\PayPalCommerce\AgenticCommerce\Response\NewCartResponse;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\AgenticCommerce\Cart\PayPalCartToCartDataAdapter;
use WooCommerce\PayPalCommerce\Button\Session\CartData;
use WP_REST_Request;
use Mockery;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\CreateCartEndpoint
 */
class CreateCartEndpointTest extends AgenticEndpointTestCase {

	public function test_create_cart_returns_201_created(): void {
		$sample_token = 'ec_token_12345';
		$cart_id      = 't_mock_cart_id_12345';
		$cart_data    = $this->cart()->with_item()->to_array();

		when( 'get_woocommerce_currency' )->justReturn( 'USD' );

		$mocks            = $this->create_mocks();
		$session_handler  = $mocks['session_handler'];
		$response_factory = $mocks['response_factory'];

		// Mock OrderEndpoint to return a PayPal order with an ID.
		$mock_order = Mockery::mock( Order::class );
		$mock_order->allows( 'id' )->andReturn( $sample_token );

		$order_endpoint = Mockery::mock( OrderEndpoint::class );
		$order_endpoint->allows( 'create' )->andReturn( $mock_order );

		// Mock CartData for the translator.
		$cart_data_mock = Mockery::mock( CartData::class );
		$cart_data_mock->allows( 'items' )->andReturn( array() );

		// Mock PayPalCartToCartDataAdapter.
		$cart_translator = Mockery::mock( PayPalCartToCartDataAdapter::class );
		$cart_translator->allows( 'translate' )->andReturn( $cart_data_mock );

		// Verify cart session is created with correct token.
		$session_handler->shouldReceive( 'create_cart_session' )
			->once()
			->withArgs( function ( $cart, $ec_token ) use ( $sample_token ) {
				return $cart instanceof PayPalCart && $ec_token === $sample_token;
			} )
			->andReturn( $cart_id );

		// Mock response factory.
		$response_factory->allows( 'new_cart' )
			->andReturnUsing( fn( $cart, $cart_id, $ec_token ) => new NewCartResponse(
				$cart,
				$cart_id,
				$ec_token
			) );

		$endpoint = new CreateCartEndpoint(
			$mocks['auth_provider'],
			$session_handler,
			$response_factory,
			$order_endpoint,
			$cart_translator
		);

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
