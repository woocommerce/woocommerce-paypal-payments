<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Button\Endpoint;

use WooCommerce\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint;

/**
 * Contract tests for the ppc-change-cart WC-AJAX endpoint, shared by the
 * v5 and v6 SDK frontends.
 *
 * @covers \WooCommerce\PayPalCommerce\Button\Endpoint\ChangeCartEndpoint
 */
class ChangeCartEndpointContractTest extends WcAjaxEndpointTestCase {

	/** @var ChangeCartEndpoint */
	private $endpoint;

	public function setUp(): void {
		parent::setUp();

		$container      = $this->bootstrapModule();
		$this->endpoint = $container->get( 'button.endpoint.change-cart' );
	}

	/**
	 * GIVEN an empty WC cart and a published simple product
	 * WHEN ppc-change-cart is called with a valid nonce and products: [{id, quantity: 2}]
	 * THEN the response is successful
	 * AND data is an array with one purchase unit with reference_id "default"
	 * AND the purchase unit amount equals price × quantity in the store currency
	 * AND the purchase unit item carries the requested quantity
	 * AND the real WC cart contains the product with the requested quantity
	 */
	public function test_adds_simple_product_and_returns_purchase_units(): void {
		$product = $this->createVirtualProduct( 10.0 );

		$response = $this->dispatchAjaxRequest(
			$this->endpoint,
			array(
				'products' => array(
					array(
						'id'       => $product->get_id(),
						'quantity' => 2,
					),
				),
			)
		);

		$this->assertTrue( $response['success'], 'ppc-change-cart must succeed: ' . $response['raw'] );

		$data = $response['data'];
		$this->assertIsArray( $data );
		$this->assertCount( 1, $data, 'Response must contain exactly one purchase unit' );
		$this->assertSame( 'default', $data[0]['reference_id'] ?? null );
		$this->assertSame( '20.00', $data[0]['amount']['value'] ?? null, 'Amount must equal price × quantity' );
		$this->assertSame( get_woocommerce_currency(), $data[0]['amount']['currency_code'] ?? null );
		$this->assertSame( 2, (int) ( $data[0]['items'][0]['quantity'] ?? 0 ) );

		$cart_items = array_values( WC()->cart->get_cart() );
		$this->assertCount( 1, $cart_items, 'WC cart must contain exactly one line item' );
		$this->assertSame( $product->get_id(), $cart_items[0]['product_id'] );
		$this->assertSame( 2, $cart_items[0]['quantity'] );
	}

	/**
	 * GIVEN a WC cart already containing product A
	 * WHEN ppc-change-cart is called with products: [B]
	 * THEN the WC cart contains only product B
	 * (the endpoint empties and repopulates the cart — the replace semantics
	 * both frontends rely on)
	 */
	public function test_replaces_existing_cart_contents(): void {
		$product_a = $this->addVirtualProductToCart( 5.0 );
		$product_b = $this->createVirtualProduct( 10.0 );

		$response = $this->dispatchAjaxRequest(
			$this->endpoint,
			array(
				'products' => array(
					array(
						'id'       => $product_b->get_id(),
						'quantity' => 1,
					),
				),
			)
		);

		$this->assertTrue( $response['success'], 'ppc-change-cart must succeed: ' . $response['raw'] );

		$product_ids = array_column( array_values( WC()->cart->get_cart() ), 'product_id' );
		$this->assertSame( array( $product_b->get_id() ), $product_ids, 'Cart must contain only the new product' );
		$this->assertNotContains( $product_a->get_id(), $product_ids );
	}

	/**
	 * GIVEN a published variable product with a "color" attribute and a "red" variation
	 * WHEN ppc-change-cart is called with products: [{id: parent, quantity: 1,
	 *      variations: [{name: "attribute_color", value: "red"}]}]
	 * THEN the WC cart contains the matching variation
	 * AND the response contains the purchase unit for it
	 */
	public function test_adds_variable_product_with_variations(): void {
		$products  = $this->createVariableProduct( 15.0 );
		$parent    = $products['parent'];
		$variation = $products['variation'];

		$response = $this->dispatchAjaxRequest(
			$this->endpoint,
			array(
				'products' => array(
					array(
						'id'         => $parent->get_id(),
						'quantity'   => 1,
						'variations' => array(
							array(
								'name'  => 'attribute_color',
								'value' => 'red',
							),
						),
					),
				),
			)
		);

		$this->assertTrue( $response['success'], 'ppc-change-cart must succeed: ' . $response['raw'] );

		$cart_items = array_values( WC()->cart->get_cart() );
		$this->assertCount( 1, $cart_items );
		$this->assertSame( $parent->get_id(), $cart_items[0]['product_id'] );
		$this->assertSame( $variation->get_id(), $cart_items[0]['variation_id'], 'Cart line must reference the matched variation' );

		$this->assertSame( '15.00', $response['data'][0]['amount']['value'] ?? null );
	}

	/**
	 * GIVEN a request body without a products field
	 * WHEN ppc-change-cart is called with a valid nonce
	 * THEN the request fails with the "Necessary fields not defined" error shape
	 */
	public function test_missing_products_field_returns_error(): void {
		$response = $this->dispatchAjaxRequest( $this->endpoint, array() );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Necessary fields not defined. Action aborted.', $response['data']['message'] ?? null );
		$this->assertSame( 0, $response['data']['code'] ?? null );
	}

	/**
	 * GIVEN a products entry referencing a non-existing product id
	 * WHEN ppc-change-cart is called with a valid nonce
	 * THEN the request fails with the "Necessary fields not defined" error shape
	 * (unknown products are filtered out, leaving an empty products list)
	 */
	public function test_unknown_product_id_returns_error(): void {
		$response = $this->dispatchAjaxRequest(
			$this->endpoint,
			array(
				'products' => array(
					array(
						'id'       => 999999999,
						'quantity' => 1,
					),
				),
			)
		);

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Necessary fields not defined. Action aborted.', $response['data']['message'] ?? null );
	}
}
