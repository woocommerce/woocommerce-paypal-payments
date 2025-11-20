<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WooCommerce\PayPalCommerce\AgenticCommerce\Response\NewCartResponse;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WP_REST_Request;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\CreateCartEndpoint
 */
class CreateCartEndpointTest extends AgenticEndpointTestCase {

	public function test_create_cart_returns_201_created(): void {
		$sample_token = 'random-string';
		$cart_id      = 't_mock_cart_id_12345';
		$cart_data    = $this->cart()->with_item()->to_array();

		when( 'wp_generate_password' )->justReturn( $sample_token );

		$mocks            = $this->create_mocks();
		$session_handler  = $mocks['session_handler'];
		$response_factory = $mocks['response_factory'];

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

		$endpoint = new CreateCartEndpoint( $mocks['auth_provider'], $session_handler, $response_factory );

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
