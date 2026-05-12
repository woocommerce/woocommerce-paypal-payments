<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\StoreSync;

use Mockery;
use Psr\Log\NullLogger;
use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\StoreSync\Endpoint\ReplaceCartEndpoint;
use WooCommerce\PayPalCommerce\StoreSync\Helper\PayPalOrderManager;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Endpoint\ReplaceCartEndpoint
 */
class ReplaceCartEndpointTest extends IntegrationMockedTestCase {

	private ReplaceCartEndpoint $endpoint;

	private AgenticSessionHandler $session_handler;

	public function setUp(): void {
		parent::setUp();

		$c = $this->getContainer();

		$this->session_handler = $c->get( 'agentic.session.handler' );

		$order_manager = Mockery::mock( PayPalOrderManager::class );
		$order_manager->allows( 'update_order' )->andReturn( true );

		$this->endpoint = new ReplaceCartEndpoint(
			$c->get( 'agentic.auth.provider' ),
			$this->session_handler,
			$c->get( 'agentic.helper.session-manager' ),
			$c->get( 'agentic.response.factory' ),
			$c->get( 'agentic.validation.processor' ),
			new NullLogger(),
			$order_manager,
			$c->get( 'agentic.store.data' )
		);
	}

	/**
	 * GIVEN a cart session exists and the PUT payload includes a payment token
	 * WHEN replace_cart() is called with a valid cart
	 * THEN the response status is 200
	 * AND payment_method.token echoes the token from the incoming payload unchanged
	 * AND id matches the cart session id
	 * AND status is INCOMPLETE
	 * AND validation_status is VALID with no validation_issues
	 * AND totals contains subtotal, shipping, tax and total with currency_code and value
	 * AND available_shipping_options is an array when present
	 */
	public function test_replace_cart_preserves_ec_token_in_response(): void {
		$product_id = wc_get_product_id_by_sku( 'DUMMY_SIMPLE_SKU_01' );
		$currency   = get_woocommerce_currency();

		// Data for create cart.
		$cart_data_1 = array(
			'items'          => array(
				array(
					'item_id'  => (string) $product_id,
					'quantity' => 1,
					'price'    => array(
						'currency_code' => $currency,
						'value'         => '10.00',
					),
				),
			),
			'payment_method' => array( 'type' => 'paypal' ),
		);
		// Data to update the existing cart (new quantity + include a token)
		$cart_data_2 = array(
			'items'          => array(
				array(
					'item_id'  => (string) $product_id,
					'quantity' => 2,
					'price'    => array(
						'currency_code' => $currency,
						'value'         => '10.00',
					),
				),
			),
			'payment_method' => array(
				'type'  => 'paypal',
				'token' => 'test-passthrough-ec-token',
			),
		);

		$initial_cart = PayPalCart::from_array( $cart_data_1, new StoreValidation() );

		$cart_id = $this->session_handler->create_cart_session( $initial_cart, '' );

		$body = (string) json_encode( $cart_data_2 );

		$request = new WP_REST_Request( 'PUT' );
		$request->set_param( 'cart_id', $cart_id );
		$request->set_body( $body );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->endpoint->replace_cart( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertSame(
			'test-passthrough-ec-token',
			$data['payment_method']['token'] ?? null,
			'Replace cart must echo the incoming payment token unchanged'
		);

		$this->assertSame( $cart_id, $data['id'] ?? null, 'Response must echo the cart id' );

		$this->assertSame( 'INCOMPLETE', $data['status'] ?? null );

		$this->assertSame( 'VALID', $data['validation_status'] ?? null );
		$this->assertIsArray( $data['validation_issues'] ?? null );
		$this->assertEmpty( $data['validation_issues'] );

		$this->assertArrayHasKey( 'totals', $data, 'Response must include calculated totals' );
		foreach ( array( 'subtotal', 'shipping', 'tax', 'total' ) as $key ) {
			$this->assertArrayHasKey( $key, $data['totals'], "totals must include $key" );
			$this->assertNotEmpty( $data['totals'][ $key ]['currency_code'] ?? '' );
			$this->assertNotEmpty( $data['totals'][ $key ]['value'] ?? '' );
		}

		$this->assertIsArray( $data['available_shipping_options'] ?? array() );
	}
}
