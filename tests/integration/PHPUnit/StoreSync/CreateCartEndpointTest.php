<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\StoreSync;

use Mockery;
use Psr\Log\NullLogger;
use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\StoreSync\Endpoint\CreateCartEndpoint;
use WooCommerce\PayPalCommerce\StoreSync\Helper\PayPalOrderManager;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;

/**
 * @covers \WooCommerce\PayPalCommerce\StoreSync\Endpoint\CreateCartEndpoint
 */
class CreateCartEndpointTest extends IntegrationMockedTestCase {

	private CreateCartEndpoint $endpoint;

	public function setUp(): void {
		parent::setUp();

		$c = $this->getContainer();

		$order_manager = Mockery::mock( PayPalOrderManager::class );
		$order_manager->allows( 'create_order' )->andReturn( 'test-ec-token' );

		$this->endpoint = new CreateCartEndpoint(
			$c->get( 'agentic.auth.provider' ),
			$c->get( 'agentic.session.handler' ),
			$c->get( 'agentic.helper.session-manager' ),
			$c->get( 'agentic.response.factory' ),
			$c->get( 'agentic.validation.processor' ),
			new NullLogger(),
			$order_manager,
			$c->get( 'agentic.store.data' )
		);
	}

	/**
	 * GIVEN a valid cart with one real product at the matching store price
	 * WHEN create_cart() is called
	 * THEN the response status is 201
	 * AND the body contains status CREATED, validation_status VALID, no validation_issues
	 * AND payment_method.token matches the token returned by the order manager
	 * AND the items array is present
	 */
	public function test_create_cart_returns_expected_shape_for_valid_cart(): void {
		$product_id = wc_get_product_id_by_sku( 'DUMMY_SIMPLE_SKU_01' );
		$currency   = get_woocommerce_currency();

		$body = (string) json_encode(
			array(
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
			)
		);

		$request = new WP_REST_Request( 'POST' );
		$request->set_body( $body );
		$request->set_header( 'Content-Type', 'application/json' );

		$response = $this->endpoint->create_cart( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'id', $data, 'Response must include a cart id' );
		$this->assertNotEmpty( $data['id'], 'Cart id must not be empty' );

		$this->assertSame( 'CREATED', $data['status'] ?? null );
		$this->assertSame( 'VALID', $data['validation_status'] ?? null );
		$this->assertEmpty( $data['validation_issues'] ?? array(), 'No validation issues expected for a valid cart' );

		$this->assertArrayHasKey( 'payment_method', $data );
		$this->assertSame( 'test-ec-token', $data['payment_method']['token'] ?? null );

		$this->assertArrayHasKey( 'items', $data, 'Response must include items' );
		$this->assertNotEmpty( $data['items'] );
	}
}
