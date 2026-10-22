<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Button\Endpoint;

use Mockery;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\OrderStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Checkout with a PayPal order that was created on the product page (Apple Pay v5 flow).
 * Starts at PayPalGateway::process_payment(): Apple Pay hands its posted checkout data to WC checkout, which calls it.
 *
 * @covers \WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway
 * @covers \WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor
 */
class ProductPageOrderCheckoutTest extends WcAjaxEndpointTestCase {

	/** @var int */
	private $created_order_count = 0;

	/** @var string[] */
	private $captured_order_ids = array();

	public function setUp(): void {
		parent::setUp();

		$this->created_order_count = 0;
		$this->captured_order_ids  = array();
	}

	public function tearDown(): void {
		unset( $_POST['paypal_order_id'], $_POST['funding_source'] );

		parent::tearDown();
	}

	/**
	 * GIVEN a buyer created an Apple Pay PayPal order through the create-order endpoint on the product page
	 * WHEN the buyer checks out and the PayPal order ID and the Apple Pay funding source are posted with the checkout data
	 * THEN the gateway captures that same PayPal order
	 * AND no second PayPal order is created
	 * AND the buyer is sent to the order-received page, not to PayPal
	 * AND the WooCommerce order is processing
	 */
	public function test_checkout_captures_order_created_on_product_page(): void {
		$container = $this->boot();

		$product  = $this->addVirtualProductToCart( 10.0 );
		$response = $this->dispatchAjaxRequest(
			$container->get( 'button.endpoint.create-order' ),
			array(
				'context'        => 'product',
				'payment_method' => 'ppcp-gateway',
				'funding_source' => 'apple_pay',
				'purchase_units' => array(
					array(
						'items' => array(
							array( 'url' => get_permalink( $product->get_id() ) ),
						),
					),
				),
			)
		);

		$this->assertTrue( $response['success'], 'ppc-create-order must succeed: ' . $response['raw'] );
		$paypal_order_id = $response['data']['id'] ?? '';
		$this->assertNotSame( '', $paypal_order_id );

		$orders_before_checkout = $this->created_order_count;
		$wc_order               = $this->pending_wc_order();

		$_POST['paypal_order_id'] = $paypal_order_id;
		$_POST['funding_source']  = 'apple_pay';
		$result                  = $container->get( 'wcgateway.paypal-gateway' )->process_payment( $wc_order->get_id() );

		$refreshed = wc_get_order( $wc_order->get_id() );
		$this->assertSame( array( $paypal_order_id ), $this->captured_order_ids );
		$this->assertSame( $orders_before_checkout, $this->created_order_count, 'Checkout must not create a second PayPal order.' );
		$this->assertSame( 'success', $result['result'] );
		$this->assertSame( $refreshed->get_checkout_order_received_url(), $result['redirect'] );
		$this->assertSame( 'processing', $refreshed->get_status() );
	}

	/**
	 * GIVEN the server never created a PayPal order for this buyer
	 * WHEN the checkout request carries a PayPal order ID that the client made up
	 * THEN that PayPal order is never captured
	 * AND the WooCommerce order is not paid
	 */
	public function test_checkout_never_captures_a_posted_order_the_server_did_not_create(): void {
		$container = $this->boot();
		$wc_order  = $this->pending_wc_order();

		$_POST['paypal_order_id'] = 'FOREIGN-PAYPAL-ORDER';
		$container->get( 'wcgateway.paypal-gateway' )->process_payment( $wc_order->get_id() );

		$this->assertSame( array(), $this->captured_order_ids );
		$this->assertSame( 'pending', wc_get_order( $wc_order->get_id() )->get_status() );
	}

	private function boot(): ContainerInterface {
		$endpoint = Mockery::mock( OrderEndpoint::class );
		$endpoint->shouldReceive( 'with_bn_code' )->andReturnSelf();
		$endpoint->shouldReceive( 'create' )->andReturnUsing(
			function ( array $purchase_units ): Order {
				++$this->created_order_count;

				return new Order(
					'PAYPAL-ORDER-' . $this->created_order_count,
					$purchase_units,
					new OrderStatus( OrderStatus::CREATED )
				);
			}
		);
		$endpoint->shouldReceive( 'order' )->andReturnUsing(
			function ( string $id ): Order {
				return $this->paypal_order( $id, OrderStatus::APPROVED );
			}
		);
		$endpoint->shouldReceive( 'patch_order_with' )->andReturnUsing(
			function ( Order $order ): Order {
				return $order;
			}
		);
		$endpoint->shouldReceive( 'capture' )->andReturnUsing(
			function ( Order $order ): Order {
				$this->captured_order_ids[] =$order->id();

				return $this->paypal_order( $order->id(), OrderStatus::COMPLETED, 'CAPTURE-1' );
			}
		);

		return $this->bootstrapWithOrderApi( $endpoint );
	}

	private function pending_wc_order(): WC_Order {
		$wc_order = $this->getConfiguredOrder(
			$this->customer_id,
			PayPalGateway::ID,
			array( 'simple' ),
			array(),
			false
		);
		$wc_order->set_status( 'pending' );
		$wc_order->save();

		return $wc_order;
	}

	private function paypal_order( string $id, string $status, ?string $capture_id = null ): Order {
		$captures = array();
		if ( $capture_id ) {
			$capture = Mockery::mock( Capture::class )->shouldIgnoreMissing();
			$capture->allows( 'id' )->andReturn( $capture_id );
			$capture->allows( 'status' )->andReturn( new CaptureStatus( CaptureStatus::COMPLETED ) );
			$captures = array( $capture );
		}

		$payments = Mockery::mock( Payments::class )->shouldIgnoreMissing();
		$payments->allows( 'captures' )->andReturn( $captures );
		$payments->allows( 'authorizations' )->andReturn( array() );

		$purchase_unit = Mockery::mock( PurchaseUnit::class )->shouldIgnoreMissing();
		$purchase_unit->allows( 'payments' )->andReturn( $capture_id ? $payments : null );

		$order = Mockery::mock( Order::class )->shouldIgnoreMissing();
		$order->allows( 'id' )->andReturn( $id );
		$order->allows( 'intent' )->andReturn( 'CAPTURE' );
		$order->allows( 'status' )->andReturn( new OrderStatus( $status ) );
		$order->allows( 'payment_source' )->andReturn( new PaymentSource( 'paypal', (object) array() ) );
		$order->allows( 'payer' )->andReturn( null );
		$order->allows( 'links' )->andReturn( array() );
		$order->allows( 'purchase_units' )->andReturn( array( $purchase_unit ) );

		return $order;
	}
}
