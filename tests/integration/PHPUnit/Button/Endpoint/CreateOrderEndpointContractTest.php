<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Button\Endpoint;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;

/**
 * Contract tests for the ppc-create-order WC-AJAX endpoint, shared by the
 * v5 and v6 SDK frontends.
 *
 * @covers \WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\CreateOrderEndpoint
 */
class CreateOrderEndpointContractTest extends WcAjaxEndpointTestCase {

	private const PAYPAL_ORDER_ID = 'TEST-PAYPAL-ORDER-123';

	/**
	 * Arguments captured from the mocked OrderEndpoint::create() call.
	 *
	 * @var array|null
	 */
	private $captured_create;

	/**
	 * Boots the container with a mocked PayPal order API whose create() returns
	 * a real Order wrapping the purchase units the endpoint built from the live
	 * cart, and captures the call arguments.
	 *
	 * @return object The ppc-create-order endpoint.
	 */
	private function endpointWithMockedCreate() {
		$this->captured_create = null;
		$captured              = &$this->captured_create;

		$order_endpoint = Mockery::mock( OrderEndpoint::class );
		$order_endpoint->shouldReceive( 'with_bn_code' )->andReturnSelf()->byDefault();
		$order_endpoint->shouldReceive( 'create' )->andReturnUsing(
			function ( array $purchase_units, string $shipping_preference ) use ( &$captured ): Order {
				$captured = array(
					'purchase_units'      => $purchase_units,
					'shipping_preference' => $shipping_preference,
				);

				return new Order(
					self::PAYPAL_ORDER_ID,
					$purchase_units,
					new OrderStatus( OrderStatus::CREATED )
				);
			}
		);

		$container = $this->bootstrapWithOrderApi( $order_endpoint );

		return $container->get( 'button.endpoint.create-order' );
	}

	/**
	 * The non-checkout contexts sharing the same create-order contract.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function nonCheckoutContextProvider(): array {
		return array(
			'cart context'    => array( 'cart' ),
			'product context' => array( 'product' ),
		);
	}

	/**
	 * GIVEN a populated WC cart (virtual product, price 10, quantity 2)
	 * WHEN ppc-create-order is called with a valid nonce and context: cart or product
	 * THEN the response contains the PayPal order id in data.id and a custom_id key
	 * AND the order is created from exactly one purchase unit reflecting the cart total
	 * AND the purchase unit custom_id is blanked (non-checkout contexts must not
	 *     allow webhook completion binding)
	 * AND no PayPal order is stored in the plugin session
	 *
	 * The product context additionally requires purchase_units[0].items[0].url
	 * in the request body (the product page URL, consumed by ReturnUrlFactory
	 * as the PayPal return URL).
	 *
	 * @dataProvider nonCheckoutContextProvider
	 *
	 * @param string $context The request context.
	 */
	public function test_non_checkout_context_returns_paypal_order_id( string $context ): void {
		$product  = $this->addVirtualProductToCart( 10.0, 2 );
		$endpoint = $this->endpointWithMockedCreate();

		$body = array(
			'context'        => $context,
			'payment_method' => 'ppcp-gateway',
			'funding_source' => 'paypal',
		);
		if ( 'product' === $context ) {
			$body['purchase_units'] = array(
				array(
					'items' => array(
						array( 'url' => get_permalink( $product->get_id() ) ),
					),
				),
			);
		}

		$response = $this->dispatchAjaxRequest( $endpoint, $body );

		$this->assertTrue( $response['success'], 'ppc-create-order must succeed: ' . $response['raw'] );
		$this->assertSame( self::PAYPAL_ORDER_ID, $response['data']['id'] ?? null, 'data.id must be the PayPal order id' );
		$this->assertArrayHasKey( 'custom_id', $response['data'] );

		$this->assertNotNull( $this->captured_create, 'The PayPal create order API must be called' );
		$this->assertCount( 1, $this->captured_create['purchase_units'] );

		$purchase_unit = $this->captured_create['purchase_units'][0];
		$this->assertInstanceOf( PurchaseUnit::class, $purchase_unit );
		$this->assertSame( '20.00', $purchase_unit->to_array()['amount']['value'], 'Amount must equal the cart total' );
		$this->assertSame( '', $purchase_unit->custom_id(), ucfirst( $context ) . ' context must blank the purchase unit custom_id' );
		$this->assertNotSame( '', $this->captured_create['shipping_preference'] );

		$session_handler = $this->getContainer()->get( 'session.handler' );
		$this->assertNull( $session_handler->order(), ucfirst( $context ) . ' context must not store a session order' );
	}

	/**
	 * GIVEN a populated WC cart
	 * WHEN ppc-create-order creates the PayPal order
	 * THEN the woocommerce_paypal_payments_create_order_endpoint_order_created
	 *      action fires once with the created Order entity and the request data
	 * (the hook the v6 SDK module consumes to store session orders for
	 * non-checkout contexts)
	 */
	public function test_create_order_endpoint_fires_order_created_action(): void {
		$this->addVirtualProductToCart( 10.0 );
		$endpoint = $this->endpointWithMockedCreate();

		$calls = $this->recordAction( 'woocommerce_paypal_payments_create_order_endpoint_order_created', 2 );

		$response = $this->dispatchAjaxRequest(
			$endpoint,
			array(
				'context'        => 'cart',
				'payment_method' => 'ppcp-gateway',
				'funding_source' => 'paypal',
			)
		);

		$this->assertTrue( $response['success'], 'ppc-create-order must succeed: ' . $response['raw'] );
		$this->assertCount( 1, $calls, 'The order_created action must fire exactly once' );

		list( $order, $data ) = $calls[0];
		$this->assertInstanceOf( Order::class, $order );
		$this->assertSame( self::PAYPAL_ORDER_ID, $order->id() );
		$this->assertIsArray( $data );
		$this->assertSame( 'cart', $data['context'] ?? null, 'The action must receive the sanitized request data' );
	}

	/**
	 * GIVEN a populated WC cart
	 * WHEN ppc-create-order is called with context: checkout, the PayPal gateway
	 *      and no redirect-less funding source
	 * THEN the created PayPal order is stored in the plugin session ('ppcp' key)
	 * AND the purchase unit keeps the session-bound custom_id
	 *      (pcp_customer_ + WC session customer id) used by the
	 *      approve-order ownership check
	 */
	public function test_checkout_context_stores_order_in_session(): void {
		$this->addVirtualProductToCart( 10.0 );
		$endpoint = $this->endpointWithMockedCreate();

		$response = $this->dispatchAjaxRequest(
			$endpoint,
			array(
				'context'        => 'checkout',
				'payment_method' => 'ppcp-gateway',
			)
		);

		$this->assertTrue( $response['success'], 'ppc-create-order must succeed: ' . $response['raw'] );
		$this->assertSame( self::PAYPAL_ORDER_ID, $response['data']['id'] ?? null );

		$session_handler = $this->getContainer()->get( 'session.handler' );
		$session_order   = $session_handler->order();
		$this->assertNotNull( $session_order, 'Checkout context must store the PayPal order in the session' );
		$this->assertSame( self::PAYPAL_ORDER_ID, $session_order->id() );

		$stored = WC()->session->get( 'ppcp' );
		$this->assertInstanceOf( Order::class, $stored['order'] ?? null, 'The session order must be stored as an Order entity under the ppcp session key' );

		$purchase_unit = $this->captured_create['purchase_units'][0];
		$this->assertSame(
			$this->sessionCustomId(),
			$purchase_unit->custom_id(),
			'Checkout context must keep the session-bound custom_id on the purchase unit'
		);
	}
}
