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
	 * AND customer and shipping_address keys exist in the response
	 * AND available_shipping_options has at least one entry with exactly one selected option
	 * AND totals subtotal/shipping/tax/total each have a non-empty value
	 * AND the sum of item price × quantity equals the subtotal (in cents)
	 */
	public function test_create_cart_returns_expected_shape_for_valid_cart(): void {
		// Minimal, valid cart (1 product and shipping address).
		$cart_data = array(
			'items'            => array(
				array(
					'variant_id' => 'DUMMY_SIMPLE_SKU_01',
					'quantity'   => 1,
				),
			),
			'shipping_address' => array(
				'address_line_1' => '456 Fictional St',
				'admin_area_2'   => 'San Jose',
				'postal_code'    => '90210',
				'country_code'   => 'US',
			),
			'payment_method'   => array( 'type' => 'paypal' ),
		);

		$body = (string) json_encode( $cart_data );

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

		$this->assertArrayHasKey( 'customer', $data, 'Response must include customer' );
		$this->assertArrayHasKey( 'shipping_address', $data, 'Response must include shipping_address' );

		$shipping_options = $data['available_shipping_options'] ?? array();
		$this->assertIsArray( $shipping_options );
		$this->assertGreaterThanOrEqual( 1, count( $shipping_options ), 'At least one shipping option must be present' );
		$selected = array_filter( $shipping_options, fn( $opt ) => ( $opt['is_selected'] ?? false ) === true );
		$this->assertCount( 1, $selected, 'Exactly one shipping option must be selected' );

		foreach ( array( 'subtotal', 'shipping', 'tax', 'total' ) as $key ) {
			$this->assertArrayHasKey( $key, $data['totals'], "totals must include $key" );
			$this->assertNotEmpty( $data['totals'][ $key ]['value'] ?? '', "totals.$key.value must not be empty" );
		}

		$items_subtotal_cents = 0;
		foreach ( $data['items'] as $item ) {
			$price    = (float) ( $item['price']['value'] ?? 0 );
			$quantity = (int) ( $item['quantity'] ?? 0 );
			$items_subtotal_cents += (int) round( $price * 100 ) * $quantity;
		}
		$subtotal_cents = (int) round( (float) ( $data['totals']['subtotal']['value'] ?? 0 ) * 100 );
		$this->assertSame( $subtotal_cents, $items_subtotal_cents, 'Sum of item price × quantity must equal totals.subtotal' );
	}
}
