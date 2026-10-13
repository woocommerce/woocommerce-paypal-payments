<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\WcGateway;

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
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\Helper\StubRedirectionException;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * @group transactions
 */
class PayerActionReturnTest extends IntegrationMockedTestCase {

	private const PAYPAL_ORDER_ID = 'PP-ORDER-PAYER-ACTION';

	private const PAYER_ID = 'PAYER-ID-1';

	public function setUp(): void {
		parent::setUp();

		if ( null === WC()->session ) {
			wc_load_cart();
		}
		WC()->session->set( 'ppcp', null );
	}

	public function tearDown(): void {
		unset( $_GET['token'], $_GET['PayerID'] );
		WC()->session->set( 'ppcp', null );

		parent::tearDown();
	}

	/**
	 * GIVEN a pending order whose capture PayPal rejects with PAYER_ACTION_REQUIRED
	 * WHEN the payment is processed
	 * THEN the order stays pending
	 * AND the buyer is sent to PayPal to approve the order again
	 * AND the buyer returning from PayPal to a store page is forwarded to the return URL endpoint
	 * with the PayPal token and the payer ID.
	 */
	public function test_payer_action_required_keeps_order_pending_and_forwards_the_return(): void {
		$wc_order = $this->pending_wc_order();

		$approved_order = $this->paypal_order( self::PAYPAL_ORDER_ID, OrderStatus::APPROVED, $wc_order );
		$endpoint       = Mockery::mock( OrderEndpoint::class );
		$endpoint->allows( 'order' )->andReturn( $approved_order );
		$endpoint->allows( 'patch_order_with' )->andReturn( $approved_order );
		$endpoint->allows( 'capture' )->andThrow( $this->payer_action_required_exception() );

		$c = $this->boot( $endpoint );
		$c->get( 'session.handler' )->replace_order( $this->session_order() );

		$result = $c->get( 'wcgateway.paypal-gateway' )->process_payment( $wc_order->get_id() );

		$this->assertSame( 'success', $result['result'] );
		$this->assertStringEndsWith( '/checkoutnow?token=' . self::PAYPAL_ORDER_ID, $result['redirect'] );
		$this->assertSame( 'pending', wc_get_order( $wc_order->get_id() )->get_status() );

		$_GET['token']   = self::PAYPAL_ORDER_ID;
		$_GET['PayerID'] = self::PAYER_ID;

		$location = $this->forwarded_location( $c );

		$this->assertNotNull( $location, 'The return was not forwarded.' );
		parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );
		$this->assertSame( 'ppc-return-url', $query['wc-ajax'] ?? null );
		$this->assertSame( self::PAYPAL_ORDER_ID, $query['token'] ?? null );
		$this->assertSame( self::PAYER_ID, $query['PayerID'] ?? null );
	}

	/**
	 * GIVEN a buyer was sent to PayPal after PAYER_ACTION_REQUIRED
	 * WHEN the buyer returns with a token that is not the order PayPal was asked to re-approve
	 * THEN the return is not forwarded.
	 */
	public function test_return_with_unknown_token_is_not_forwarded(): void {
		$wc_order       = $this->pending_wc_order();
		$approved_order = $this->paypal_order( self::PAYPAL_ORDER_ID, OrderStatus::APPROVED, $wc_order );
		$endpoint       = Mockery::mock( OrderEndpoint::class );
		$endpoint->allows( 'order' )->andReturn( $approved_order );
		$endpoint->allows( 'patch_order_with' )->andReturn( $approved_order );
		$endpoint->allows( 'capture' )->andThrow( $this->payer_action_required_exception() );

		$c = $this->boot( $endpoint );
		$c->get( 'session.handler' )->replace_order( $this->session_order() );
		$c->get( 'wcgateway.paypal-gateway' )->process_payment( $wc_order->get_id() );

		$_GET['token']   = 'SOME-OTHER-ORDER';
		$_GET['PayerID'] = self::PAYER_ID;

		$this->assertNull( $this->forwarded_location( $c ) );
	}

	/**
	 * GIVEN a pending order and a PayPal order the buyer has approved again
	 * AND PayPal accepts the capture
	 * WHEN the pending order is processed again, as the return endpoint does
	 * THEN the order is processing
	 * AND the buyer is redirected to the order-received page.
	 */
	public function test_processing_a_pending_order_after_reapproval_completes_it(): void {
		$wc_order = $this->pending_wc_order();
		$wc_order->update_meta_data( PayPalGateway::ORDER_ID_META_KEY, self::PAYPAL_ORDER_ID );
		$wc_order->save();

		$approved_order  = $this->paypal_order( self::PAYPAL_ORDER_ID, OrderStatus::APPROVED, $wc_order );
		$captured_order  = $this->paypal_order( self::PAYPAL_ORDER_ID, OrderStatus::COMPLETED, $wc_order, 'CAPTURE-1' );
		$endpoint        = Mockery::mock( OrderEndpoint::class );
		$endpoint->allows( 'order' )->andReturn( $approved_order );
		$endpoint->allows( 'patch_order_with' )->andReturn( $approved_order );
		$endpoint->allows( 'capture' )->andReturn( $captured_order );

		$result = $this->boot( $endpoint )->get( 'wcgateway.paypal-gateway' )->process_payment( $wc_order->get_id() );

		$refreshed = wc_get_order( $wc_order->get_id() );
		$this->assertSame( 'success', $result['result'] );
		$this->assertSame( $refreshed->get_checkout_order_received_url(), $result['redirect'] );
		$this->assertSame( 'processing', $refreshed->get_status() );
		$this->assertSame( 'CAPTURE-1', $refreshed->get_transaction_id() );
	}

	/**
	 * GIVEN an order that a webhook already captured, so it has a transaction ID
	 * WHEN the order is processed again, as the return endpoint does
	 * THEN the buyer is redirected to the order-received page
	 * AND PayPal is not asked to capture a second time.
	 */
	public function test_processing_an_already_captured_order_does_not_capture_again(): void {
		$wc_order = $this->pending_wc_order();
		$wc_order->update_meta_data( PayPalGateway::ORDER_ID_META_KEY, self::PAYPAL_ORDER_ID );
		$wc_order->set_transaction_id( 'CAPTURE-FROM-WEBHOOK' );
		$wc_order->set_status( 'processing' );
		$wc_order->save();

		$approved_order = $this->paypal_order( self::PAYPAL_ORDER_ID, OrderStatus::APPROVED, $wc_order );
		$endpoint       = Mockery::mock( OrderEndpoint::class );
		$endpoint->allows( 'order' )->andReturn( $approved_order );
		$endpoint->allows( 'patch_order_with' )->andReturn( $approved_order );
		$endpoint->shouldNotReceive( 'capture' );

		$result = $this->boot( $endpoint )->get( 'wcgateway.paypal-gateway' )->process_payment( $wc_order->get_id() );

		$refreshed = wc_get_order( $wc_order->get_id() );
		$this->assertSame( 'success', $result['result'] );
		$this->assertSame( $refreshed->get_checkout_order_received_url(), $result['redirect'] );
		$this->assertSame( 'processing', $refreshed->get_status() );
	}

	private function boot( OrderEndpoint $endpoint ): ContainerInterface {
		return $this->bootstrapModule(
			array(
				'api.endpoint.order' => static function () use ( $endpoint ) {
					return $endpoint;
				},
			)
		);
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

	/**
	 * A real order, because the session serializes whatever it stores.
	 */
	private function session_order(): Order {
		return new Order( self::PAYPAL_ORDER_ID, array(), new OrderStatus( OrderStatus::APPROVED ) );
	}

	private function payer_action_required_exception(): PayPalApiException {
		return new PayPalApiException(
			(object) array(
				'name'    => 'UNPROCESSABLE_ENTITY',
				'message' => 'The requested action could not be performed.',
				'details' => array(
					(object) array(
						'issue'       => 'PAYER_ACTION_REQUIRED',
						'description' => 'Payer must re-approve the order.',
					),
				),
			),
			422
		);
	}

	private function paypal_order( string $id, string $status, WC_Order $wc_order, ?string $capture_id = null ): Order {
		$payments = Mockery::mock( Payments::class )->shouldIgnoreMissing();
		$captures = array();
		if ( $capture_id ) {
			$capture = Mockery::mock( Capture::class )->shouldIgnoreMissing();
			$capture->allows( 'id' )->andReturn( $capture_id );
			$capture->allows( 'status' )->andReturn( new CaptureStatus( CaptureStatus::COMPLETED ) );
			$captures = array( $capture );
		}
		$payments->allows( 'captures' )->andReturn( $captures );
		$payments->allows( 'authorizations' )->andReturn( array() );

		$purchase_unit = Mockery::mock( PurchaseUnit::class )->shouldIgnoreMissing();
		$purchase_unit->allows( 'custom_id' )->andReturn( (string) $wc_order->get_id() );
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

	private function forwarded_location( ContainerInterface $c ): ?string {
		try {
			$c->get( 'wcgateway.payer-action-return-forwarder' )->maybe_forward();
		} catch ( StubRedirectionException $redirection ) {
			return $redirection->getMessage();
		}

		return null;
	}
}
