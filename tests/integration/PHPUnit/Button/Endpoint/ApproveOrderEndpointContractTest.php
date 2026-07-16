<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Button\Endpoint;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\Tests\Integration\Fixtures\PayPalOrderPresets;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Contract tests for the ppc-approve-order WC-AJAX endpoint, shared by the
 * v5 and v6 SDK frontends.
 *
 * @covers \WooCommerce\PayPalCommerce\OrderEndpoints\Endpoint\ApproveOrderEndpoint
 */
class ApproveOrderEndpointContractTest extends WcAjaxEndpointTestCase {

	private const PAYPAL_ORDER_ID = 'TEST-PAYPAL-ORDER-123';

	/** @var ContainerInterface */
	protected $container;

	/**
	 * Boots the container with a mocked PayPal order API serving an APPROVED
	 * order (no payment source, so the card/3DS branch is skipped) and the
	 * follow-up calls the order processor performs on the happy path.
	 *
	 * final_review_enabled is forced off so the should_create_wc_order branch
	 * is deterministic regardless of store settings.
	 *
	 * @param string|null $custom_id The purchase unit custom_id of the fetched order,
	 *                               null for the session-bound one (computed at call time).
	 * @return object The ppc-approve-order endpoint.
	 */
	private function endpointWithApprovedOrder( ?string $custom_id = null ) {
		$order_endpoint = Mockery::mock( OrderEndpoint::class );

		$container = $this->bootstrapWithOrderApi(
			$order_endpoint,
			array(
				'blocks.settings.final_review_enabled' => static function (): bool {
					return false;
				},
			)
		);

		$order_factory = $container->get( 'api.factory.order' );
		$approved      = $order_factory->from_paypal_response(
			json_decode(
				(string) wp_json_encode(
					PayPalOrderPresets::order( self::PAYPAL_ORDER_ID, 'APPROVED', $custom_id ?? $this->sessionCustomId() )
				)
			)
		);
		$completed     = $order_factory->from_paypal_response(
			json_decode(
				(string) wp_json_encode(
					PayPalOrderPresets::completedWithCapture( self::PAYPAL_ORDER_ID, $custom_id ?? $this->sessionCustomId() )
				)
			)
		);

		$order_endpoint->shouldReceive( 'order' )->with( self::PAYPAL_ORDER_ID )->andReturn( $approved );
		$order_endpoint->shouldReceive( 'patch_order_with' )->andReturnUsing(
			static function ( Order $order_to_update ): Order {
				return $order_to_update;
			}
		)->byDefault();
		$order_endpoint->shouldReceive( 'capture' )->andReturn( $completed )->byDefault();

		$this->container = $container;

		return $container->get( 'button.endpoint.approve-order' );
	}

	/**
	 * GIVEN an APPROVED PayPal order owned by the current WC session
	 * AND a WC cart with a virtual product
	 * WHEN ppc-approve-order is called with should_create_wc_order: true
	 * THEN a WC order is created from the cart, paid via the PayPal gateway
	 * AND the response contains its order-received URL in data.order_received_url
	 * AND the plugin session is consumed by the successful payment
	 *     (ProcessPaymentTrait destroys the session data on success)
	 * AND chosen_payment_method is pinned to the PayPal gateway
	 */
	public function test_creates_wc_order_and_returns_received_url(): void {
		$product          = $this->addVirtualProductToCart( 10.0 );
		$endpoint         = $this->endpointWithApprovedOrder();
		$order_ids_before = $this->currentWcOrderIds();

		$response = $this->dispatchAjaxRequest(
			$endpoint,
			array(
				'order_id'               => self::PAYPAL_ORDER_ID,
				'funding_source'         => 'paypal',
				'should_create_wc_order' => true,
			)
		);

		$this->assertTrue( $response['success'], 'ppc-approve-order must succeed: ' . $response['raw'] );

		$new_order_ids = array_values( array_diff( $this->currentWcOrderIds(), $order_ids_before ) );
		$this->assertCount( 1, $new_order_ids, 'Exactly one WC order must be created' );

		$wc_order = wc_get_order( $new_order_ids[0] );
		$this->trackWcOrder( $wc_order->get_id() );

		$this->assertSame(
			$wc_order->get_checkout_order_received_url(),
			$response['data']['order_received_url'] ?? null,
			'data.order_received_url must be the order-received URL of the created WC order'
		);
		$this->assertStringContainsString( 'order-received', (string) ( $response['data']['order_received_url'] ?? '' ) );

		$this->assertSame( 'ppcp-gateway', $wc_order->get_payment_method() );
		$items = array_values( $wc_order->get_items() );
		$this->assertCount( 1, $items );
		$this->assertSame( $product->get_id(), $items[0]->get_product_id(), 'The WC order line items must match the cart' );

		$this->assertContains(
			$wc_order->get_status(),
			array( 'processing', 'completed' ),
			'The happy capture path must complete the payment'
		);

		$session_handler = $this->container->get( 'session.handler' );
		$this->assertNull( $session_handler->order(), 'The successful payment must consume the session order' );
		$this->assertSame( 'ppcp-gateway', WC()->session->get( 'chosen_payment_method' ) );
	}

	/**
	 * GIVEN an APPROVED PayPal order owned by the current WC session
	 * WHEN ppc-approve-order is called without should_create_wc_order
	 * THEN the response is a plain success with no data payload
	 * AND no WC order is created
	 * AND the PayPal order and funding source are stored in the plugin session
	 *     and chosen_payment_method is pinned
	 * (the v5 classic continuation contract: Place Order later processes the
	 * session order)
	 */
	public function test_without_should_create_wc_order_stores_session_and_returns_plain_success(): void {
		$this->addVirtualProductToCart( 10.0 );
		$endpoint         = $this->endpointWithApprovedOrder();
		$order_ids_before = $this->currentWcOrderIds();

		$response = $this->dispatchAjaxRequest(
			$endpoint,
			array(
				'order_id'       => self::PAYPAL_ORDER_ID,
				'funding_source' => 'paypal',
			)
		);

		$this->assertTrue( $response['success'], 'ppc-approve-order must succeed: ' . $response['raw'] );
		$this->assertNull( $response['data'], 'The plain success response must carry no data payload' );

		$this->assertSame( array(), array_diff( $this->currentWcOrderIds(), $order_ids_before ), 'No WC order must be created' );

		$session_handler = $this->container->get( 'session.handler' );
		$this->assertSame( self::PAYPAL_ORDER_ID, $session_handler->order() ? $session_handler->order()->id() : null );
		$this->assertSame( 'paypal', $session_handler->funding_source(), 'The funding source must be stored in the plugin session' );
		$this->assertSame( 'ppcp-gateway', WC()->session->get( 'chosen_payment_method' ) );
	}

	/**
	 * GIVEN a PayPal order whose custom_id binds it to a DIFFERENT WC session
	 *       (pcp_customer_ prefix with a foreign session id)
	 * WHEN ppc-approve-order is called with that order id
	 * THEN the request is rejected with message "Order validation failed."
	 * AND no WC order is created and no order is stored in the session
	 */
	public function test_session_ownership_mismatch_is_rejected(): void {
		$this->addVirtualProductToCart( 10.0 );
		$endpoint         = $this->endpointWithApprovedOrder( 'pcp_customer_someone-elses-session-id' );
		$order_ids_before = $this->currentWcOrderIds();

		$response = $this->dispatchAjaxRequest(
			$endpoint,
			array(
				'order_id'               => self::PAYPAL_ORDER_ID,
				'funding_source'         => 'paypal',
				'should_create_wc_order' => true,
			)
		);

		$this->assertFalse( $response['success'], 'A foreign session order must be rejected' );
		$this->assertSame( 'Order validation failed.', $response['data']['message'] ?? null );

		$this->assertSame( array(), array_diff( $this->currentWcOrderIds(), $order_ids_before ), 'No WC order must be created' );
		$this->assertNull( $this->container->get( 'session.handler' )->order(), 'No order must be stored in the session' );
	}

	/**
	 * GIVEN a PayPal order whose custom_id does NOT start with pcp_customer_
	 *       (e.g. a plain WC order id)
	 * WHEN ppc-approve-order is called
	 * THEN the ownership check is skipped and the request succeeds
	 */
	public function test_non_prefixed_custom_id_skips_ownership_check(): void {
		$this->addVirtualProductToCart( 10.0 );
		$endpoint = $this->endpointWithApprovedOrder( '123' );

		$response = $this->dispatchAjaxRequest(
			$endpoint,
			array(
				'order_id'       => self::PAYPAL_ORDER_ID,
				'funding_source' => 'paypal',
			)
		);

		$this->assertTrue( $response['success'], 'A non-session custom_id must skip the ownership check: ' . $response['raw'] );
	}

	/**
	 * GIVEN a request body without an order_id
	 * WHEN ppc-approve-order is called with a valid nonce
	 * THEN the request is rejected with message "No order id given"
	 * AND no PayPal API call is made
	 */
	public function test_missing_order_id_returns_error(): void {
		$order_endpoint = Mockery::mock( OrderEndpoint::class );
		$order_endpoint->shouldNotReceive( 'order' );

		$container = $this->bootstrapWithOrderApi( $order_endpoint );
		$endpoint  = $container->get( 'button.endpoint.approve-order' );

		$response = $this->dispatchAjaxRequest( $endpoint, array() );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'No order id given', $response['data']['message'] ?? null );
	}
}
