<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WooCommerce\PayPalCommerce\TestCase;
use WP_REST_Request;
use function Brain\Monkey\Functions\when;

/**
 * @covers CreateCartEndpoint
 */
class CreateCartEndpointTest extends TestCase {

	public function test_create_cart_returns_valid_response(): void {
		$endpoint = new CreateCartEndpoint();

		when( 'wp_generate_password' )->justReturn( 'random-string' );
		$request = new WP_REST_Request( 'POST', '/wp-json/paypal/v1/merchant-cart' );
		$request->set_body( json_encode( array(
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
		) ) );

		$response = $endpoint->create_cart( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data['validation_issues'] );
		$this->assertEmpty( $data['validation_issues'] );
		$this->assertSame( 'CREATED', $data['status'] );
		$this->assertSame( 'VALID', $data['validation_status'] );
	}
}
