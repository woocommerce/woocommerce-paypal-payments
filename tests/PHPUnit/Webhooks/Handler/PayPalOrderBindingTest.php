<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Mockery;
use Psr\Log\NullLogger;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Authorization;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\FundingSource\FundingSourceRenderer;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\RefundFeesUpdater;
use WooCommerce\PayPalCommerce\WcGateway\Processor\OrderProcessor;
use WP_REST_Request;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * The custom_id of a webhook event can name any WC order, so the handlers must touch
 * only the WC order for which the PayPal order of the event was saved.
 *
 * @covers \WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandlerTrait
 */
class PayPalOrderBindingTest extends TestCase
{
	private const WC_ORDER_ID           = '15';
	private const SAVED_ORDER           = 'SAVED-PAYPAL-ORDER';
	private const FOREIGN_ORDER         = 'FOREIGN-PAYPAL-ORDER';
	private const SAVED_CAPTURE         = 'SAVED-CAPTURE';
	private const FOREIGN_CAPTURE       = 'FOREIGN-CAPTURE';
	private const SAVED_AUTHORIZATION   = 'SAVED-AUTHORIZATION';
	private const FOREIGN_AUTHORIZATION = 'FOREIGN-AUTHORIZATION';
	// Covers the WC order total, so the captured amount check passes.
	private const CAPTURED_AMOUNT = array( 'value' => '1.00', 'currency_code' => 'USD' );

	/** @var OrderEndpoint&\Mockery\MockInterface */
	private $order_endpoint;

	public function setUp(): void
	{
		parent::setUp();

		$this->order_endpoint = Mockery::mock( OrderEndpoint::class );
	}

	/**
	 * @dataProvider foreign_events
	 */
	public function test_event_of_foreign_paypal_order_does_not_touch_the_wc_order( string $handler, array $resource, string $saved_order ): void
	{
		// Any call other than get_meta() fails the test.
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'get_meta' )->with( PayPalGateway::ORDER_ID_META_KEY )->andReturn( $saved_order );
		$wc_order->allows( 'get_meta' )->with( '_agentic_commerce' )->andReturn( '' );
		$wc_order->allows( 'get_meta' )->with( PayPalGateway::REFUNDS_META_KEY )->andReturn( array() );
		$this->order_endpoint->allows( 'order' )->andReturn( $this->saved_paypal_order() );

		when( 'wc_get_order' )->justReturn( $wc_order );
		when( 'wc_get_orders' )->justReturn( array( $wc_order ) );
		expect( 'wc_create_refund' )->never();
		expect( 'do_action' )->never();

		$response = $this->make_handler( $handler )->handle_request( $this->make_request( $resource ) );

		$this->assertFalse( $response->get_data()['success'] );
	}

	public function foreign_events(): array
	{
		$events = array();
		foreach ( array( 'saved' => self::SAVED_ORDER, 'no' => '' ) as $name => $saved_order ) {
			$events += array(
				"CHECKOUT.ORDER.COMPLETED, $name PayPal order"            => array( CheckoutOrderCompleted::class, $this->order_resource( 'id', self::FOREIGN_ORDER ), $saved_order ),
				"CHECKOUT.PAYMENT-APPROVAL.REVERSED, $name PayPal order"  => array( CheckoutPaymentApprovalReversed::class, $this->order_resource( 'order_id', self::FOREIGN_ORDER ), $saved_order ),
				"PAYMENT.CAPTURE.COMPLETED, $name PayPal order"           => array( PaymentCaptureCompleted::class, $this->capture_resource( self::FOREIGN_ORDER ), $saved_order ),
				"PAYMENT.CAPTURE.COMPLETED w/o order, $name PayPal order" => array( PaymentCaptureCompleted::class, $this->capture_resource( '', self::FOREIGN_CAPTURE, $this->authorization_href( self::FOREIGN_AUTHORIZATION ) ), $saved_order ),
				"PAYMENT.CAPTURE.COMPLETED up link to foreign order, $name PayPal order" => array( PaymentCaptureCompleted::class, $this->capture_resource( '', self::FOREIGN_CAPTURE, $this->paypal_order_href( self::FOREIGN_ORDER ) ), $saved_order ),
				"PAYMENT.CAPTURE.PENDING, $name PayPal order"             => array( PaymentCapturePending::class, $this->capture_resource( self::FOREIGN_ORDER ), $saved_order ),
				"PAYMENT.CAPTURE.DENIED, $name PayPal order"              => array( PaymentCaptureReversed::class, $this->capture_resource( self::FOREIGN_ORDER ), $saved_order ),
				"PAYMENT.CAPTURE.REVERSED, $name PayPal order"            => array( PaymentCaptureReversed::class, $this->refund_resource( self::FOREIGN_CAPTURE ), $saved_order ),
				"PAYMENT.CAPTURE.REFUNDED, $name PayPal order"            => array( PaymentCaptureRefunded::class, $this->refund_resource( self::FOREIGN_CAPTURE ), $saved_order ),
			);
		}

		return $events;
	}

	/**
	 * @testWith ["SAVED-PAYPAL-ORDER", 1]
	 *           ["FOREIGN-PAYPAL-ORDER", 0]
	 */
	public function test_order_approved_processes_only_the_wc_order_with_the_paypal_order( string $paypal_order_id, int $times ): void
	{
		$wc_order = $this->make_wc_order( self::SAVED_ORDER );
		$this->order_endpoint->allows( 'order' )->with( $paypal_order_id )->andReturn( Mockery::mock( Order::class ) );

		$order_processor = Mockery::mock( OrderProcessor::class );
		$order_processor->expects( 'process' )->with( $wc_order )->times( $times );

		when( 'wc_get_orders' )->justReturn( array( $wc_order ) );

		$sut = new CheckoutOrderApproved(
			new NullLogger(),
			$this->order_endpoint,
			Mockery::mock( SessionHandler::class ),
			Mockery::mock( FundingSourceRenderer::class ),
			$order_processor
		);

		$response = $sut->handle_request( $this->make_request( $this->order_resource( 'id', $paypal_order_id ) ) );

		$this->assertSame( (bool) $times, $response->get_data()['success'] );
	}

	public function test_order_completed_pays_only_the_wc_order_with_the_paypal_order(): void
	{
		$wc_order = $this->make_wc_order( self::SAVED_ORDER );
		$wc_order->expects( 'payment_complete' );
		$other_wc_order = $this->make_wc_order( self::FOREIGN_ORDER );
		$other_wc_order->expects( 'payment_complete' )->never();

		when( 'wc_get_orders' )->justReturn( array( $other_wc_order, $wc_order ) );

		$response = $this->make_handler( CheckoutOrderCompleted::class )
			->handle_request( $this->make_request( $this->order_resource( 'id', self::SAVED_ORDER ) ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	public function test_approval_reversed_fails_the_wc_order_with_the_paypal_order(): void
	{
		$wc_order = $this->make_wc_order( self::SAVED_ORDER );
		$wc_order->allows( 'is_paid' )->andReturn( false );
		$wc_order->expects( 'update_status' )->with( 'failed', Mockery::any() );

		when( 'wc_get_orders' )->justReturn( array( $wc_order ) );

		$response = $this->make_handler( CheckoutPaymentApprovalReversed::class )
			->handle_request( $this->make_request( $this->order_resource( 'order_id', self::SAVED_ORDER ) ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	public function test_capture_completed_pays_the_wc_order_with_the_paypal_order(): void
	{
		$wc_order = $this->make_wc_order( self::SAVED_ORDER );
		$wc_order->allows( 'get_status' )->andReturn( 'on-hold' );
		$wc_order->expects( 'payment_complete' );
		$this->order_endpoint->allows( 'order' )->with( self::SAVED_ORDER )->andReturn( $this->saved_paypal_order() );

		when( 'wc_get_order' )->justReturn( $wc_order );

		$response = $this->make_handler( PaymentCaptureCompleted::class )
			->handle_request( $this->make_request( $this->capture_resource( self::SAVED_ORDER ) ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	public function test_capture_completed_without_order_id_pays_the_wc_order_with_the_authorization(): void
	{
		$wc_order = $this->make_wc_order( self::SAVED_ORDER );
		$wc_order->allows( 'get_status' )->andReturn( 'on-hold' );
		$wc_order->expects( 'payment_complete' );
		$this->order_endpoint->allows( 'order' )->andReturn( $this->saved_paypal_order() );

		when( 'wc_get_order' )->justReturn( $wc_order );

		$resource = $this->capture_resource( '', 'CAPTURE-OF-AUTHORIZATION', $this->authorization_href( self::SAVED_AUTHORIZATION ) );
		$response = $this->make_handler( PaymentCaptureCompleted::class )
			->handle_request( $this->make_request( $resource ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	public function test_capture_completed_without_order_id_and_authorization_is_refused_even_for_a_saved_capture(): void
	{
		$wc_order = $this->make_wc_order( self::SAVED_ORDER );
		$wc_order->allows( 'get_status' )->andReturn( 'on-hold' );
		$wc_order->expects( 'payment_complete' )->never();
		$this->order_endpoint->allows( 'order' )->andReturn( $this->saved_paypal_order() );

		when( 'wc_get_order' )->justReturn( $wc_order );

		$response = $this->make_handler( PaymentCaptureCompleted::class )
			->handle_request( $this->make_request( $this->capture_resource( '', self::SAVED_CAPTURE ) ) );

		$this->assertFalse( $response->get_data()['success'] );
	}

	public function test_capture_pending_holds_the_wc_order_with_the_paypal_order(): void
	{
		$wc_order = $this->make_wc_order( self::SAVED_ORDER );
		$wc_order->allows( 'get_status' )->andReturn( 'pending' );
		$wc_order->expects( 'update_status' )->with( 'on-hold', Mockery::any() );

		when( 'wc_get_order' )->justReturn( $wc_order );

		$response = $this->make_handler( PaymentCapturePending::class )
			->handle_request( $this->make_request( $this->capture_resource( self::SAVED_ORDER ) ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * @dataProvider reversing_resources
	 */
	public function test_capture_reversed_cancels_the_wc_order_with_the_paypal_order( array $resource ): void
	{
		$wc_order = $this->make_wc_order( self::SAVED_ORDER );
		$wc_order->expects( 'update_status' )->with( 'cancelled', Mockery::any() )->andReturn( true );
		$this->order_endpoint->allows( 'order' )->with( self::SAVED_ORDER )->andReturn( $this->saved_paypal_order() );

		when( 'wc_get_order' )->justReturn( $wc_order );

		$response = $this->make_handler( PaymentCaptureReversed::class )->handle_request( $this->make_request( $resource ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	public function reversing_resources(): array
	{
		return array(
			'PAYMENT.CAPTURE.DENIED'   => array( $this->capture_resource( self::SAVED_ORDER ) ),
			'PAYMENT.CAPTURE.REVERSED' => array( $this->refund_resource( self::SAVED_CAPTURE ) ),
		);
	}

	public function test_capture_refunded_refunds_the_wc_order_with_the_capture(): void
	{
		$wc_order = $this->make_wc_order( self::SAVED_ORDER );
		$this->order_endpoint->allows( 'order' )->with( self::SAVED_ORDER )->andReturn( $this->saved_paypal_order() );
		$this->expect_refund_created();

		when( 'wc_get_order' )->justReturn( $wc_order );

		$response = $this->make_handler( PaymentCaptureRefunded::class )
			->handle_request( $this->make_request( $this->refund_resource( self::SAVED_CAPTURE ) ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * GIVEN a capture event without an order ID whose up link is the PayPal order saved for the WC order
	 * WHEN PAYMENT.CAPTURE.COMPLETED is handled
	 * THEN the WC order is paid without asking PayPal which payments the order has
	 */
	public function test_capture_completed_with_up_link_to_the_saved_paypal_order_pays_without_api_call(): void
	{
		$wc_order = $this->make_wc_order( self::SAVED_ORDER );
		$wc_order->allows( 'get_status' )->andReturn( 'on-hold' );
		$wc_order->expects( 'payment_complete' )->once();
		$this->order_endpoint->allows( 'order' )->andThrow( new RuntimeException( 'PayPal is unreachable' ) );

		when( 'wc_get_order' )->justReturn( $wc_order );

		$resource = $this->capture_resource( '', self::FOREIGN_CAPTURE, $this->paypal_order_href( self::SAVED_ORDER ) );
		$response = $this->make_handler( PaymentCaptureCompleted::class )->handle_request( $this->make_request( $resource ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * GIVEN a refund that is already recorded in the WC order refunds meta
	 * WHEN PAYMENT.CAPTURE.REFUNDED is handled again
	 * THEN it succeeds without asking PayPal and without creating another refund
	 */
	public function test_capture_refunded_already_handled_succeeds_without_api_call_or_second_refund(): void
	{
		$wc_order = $this->make_wc_order( self::SAVED_ORDER, array( PayPalGateway::REFUNDS_META_KEY => array( 'REFUND' ) ) );
		$this->order_endpoint->expects( 'order' )->never();

		when( 'wc_get_order' )->justReturn( $wc_order );
		expect( 'wc_create_refund' )->never();

		$response = $this->make_handler( PaymentCaptureRefunded::class )
			->handle_request( $this->make_request( $this->refund_resource( self::SAVED_CAPTURE ) ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * GIVEN an older agentic commerce order with the PayPal order ID only in _paypal_order_id
	 * WHEN PAYMENT.CAPTURE.COMPLETED for that PayPal order is handled
	 * THEN the WC order is paid
	 * AND the capture completed action receives that PayPal order ID
	 */
	public function test_capture_completed_pays_an_older_agentic_order_and_passes_its_paypal_order_id(): void
	{
		$wc_order = $this->make_wc_order( '', $this->older_agentic_meta() );
		$wc_order->allows( 'get_status' )->andReturn( 'on-hold' );
		$wc_order->expects( 'payment_complete' )->once();
		$this->order_endpoint->allows( 'order' )->with( self::SAVED_ORDER )->andReturn( $this->saved_paypal_order() );

		when( 'wc_get_order' )->justReturn( $wc_order );
		\Brain\Monkey\Actions\expectDone( 'woocommerce_paypal_payments_payment_capture_completed_webhook_handler' )
			->once()
			->with( $wc_order, self::SAVED_ORDER );

		$response = $this->make_handler( PaymentCaptureCompleted::class )
			->handle_request( $this->make_request( $this->capture_resource( self::SAVED_ORDER ) ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * GIVEN an older agentic commerce order with the PayPal order ID only in _paypal_order_id
	 * WHEN PAYMENT.CAPTURE.REFUNDED is matched to it through the up link
	 * THEN the saved PayPal order is fetched by that ID and the refund is created
	 */
	public function test_capture_refunded_refunds_an_older_agentic_order_matched_through_its_paypal_order(): void
	{
		$wc_order = $this->make_wc_order( '', $this->older_agentic_meta() );
		$this->order_endpoint->expects( 'order' )->with( self::SAVED_ORDER )->andReturn( $this->saved_paypal_order() );
		$this->expect_refund_created();

		when( 'wc_get_order' )->justReturn( $wc_order );

		$response = $this->make_handler( PaymentCaptureRefunded::class )
			->handle_request( $this->make_request( $this->refund_resource( self::SAVED_CAPTURE ) ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * GIVEN an order with _paypal_order_id but not marked as agentic commerce and without the PayPal order ID meta
	 * WHEN PAYMENT.CAPTURE.COMPLETED for that PayPal order is handled
	 * THEN the event is refused and the WC order is not paid
	 */
	public function test_capture_completed_is_refused_when_only_a_non_agentic_order_has_paypal_order_id_fallback_meta(): void
	{
		$wc_order = $this->make_wc_order( '', array( '_paypal_order_id' => self::SAVED_ORDER ) );
		$wc_order->allows( 'get_status' )->andReturn( 'on-hold' );
		$wc_order->expects( 'payment_complete' )->never();

		when( 'wc_get_order' )->justReturn( $wc_order );

		$response = $this->make_handler( PaymentCaptureCompleted::class )
			->handle_request( $this->make_request( $this->capture_resource( self::SAVED_ORDER ) ) );

		$this->assertFalse( $response->get_data()['success'] );
	}

	private function older_agentic_meta(): array
	{
		return array(
			'_agentic_commerce' => '1',
			'_paypal_order_id'  => self::SAVED_ORDER,
		);
	}

	private function expect_refund_created(): void
	{
		$refund = Mockery::mock( 'WC_Order_Refund' );
		$refund->allows( 'get_amount' )->andReturn( '1.00' );

		expect( 'wc_create_refund' )
			->once()
			->with( array( 'order_id' => (int) self::WC_ORDER_ID, 'amount' => '1.00' ) )
			->andReturn( $refund );
	}

	private function make_handler( string $class ): RequestHandler
	{
		$logger = new NullLogger();

		switch ( $class ) {
			case PaymentCaptureCompleted::class:
			case PaymentCapturePending::class:
			case PaymentCaptureReversed::class:
				return new $class( $logger, $this->order_endpoint );
			case PaymentCaptureRefunded::class:
				$refund_fees_updater = Mockery::mock( RefundFeesUpdater::class );
				$refund_fees_updater->allows( 'update' );
				return new $class( $logger, $refund_fees_updater, $this->order_endpoint );
			default:
				return new $class( $logger );
		}
	}

	/**
	 * @return WC_Order&\Mockery\MockInterface
	 */
	private function make_wc_order( string $saved_order, array $meta = array() )
	{
		$meta = array_merge(
			array(
				PayPalGateway::ORDER_ID_META_KEY => $saved_order,
				'_agentic_commerce'              => '',
				'_paypal_order_id'               => '',
				PayPalGateway::REFUNDS_META_KEY  => array(),
			),
			$meta
		);

		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'get_id' )->andReturn( (int) self::WC_ORDER_ID );
		$wc_order->allows( 'get_meta' )->andReturnUsing( static fn( $key ) => $meta[ $key ] ?? '' );
		$wc_order->allows( 'get_payment_method' )->andReturn( PayPalGateway::ID );
		$wc_order->allows( 'get_total' )->andReturn( '1.00' );
		$wc_order->allows( 'get_currency' )->andReturn( 'USD' );
		$wc_order->allows( 'needs_payment' )->andReturn( true );
		$wc_order->allows( 'add_order_note' );
		$wc_order->allows( 'update_meta_data' );
		$wc_order->allows( 'set_transaction_id' );
		$wc_order->allows( 'save' );

		return $wc_order;
	}

	/**
	 * The PayPal order with the ID in the WC order meta, as PayPal returns it.
	 */
	private function saved_paypal_order(): Order
	{
		$capture = Mockery::mock( Capture::class );
		$capture->allows( 'id' )->andReturn( self::SAVED_CAPTURE );
		$capture->allows( 'status' )->andReturn( new CaptureStatus( CaptureStatus::COMPLETED ) );

		$authorization = Mockery::mock( Authorization::class );
		$authorization->allows( 'id' )->andReturn( self::SAVED_AUTHORIZATION );

		$payments = Mockery::mock( Payments::class );
		$payments->allows( 'captures' )->andReturn( array( $capture ) );
		$payments->allows( 'authorizations' )->andReturn( array( $authorization ) );

		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->allows( 'payments' )->andReturn( $payments );

		$order = Mockery::mock( Order::class );
		$order->allows( 'purchase_units' )->andReturn( array( $purchase_unit ) );

		return $order;
	}

	private function order_resource( string $id_key, string $paypal_order_id ): array
	{
		return array(
			$id_key          => $paypal_order_id,
			'purchase_units' => array(
				array(
					'custom_id' => self::WC_ORDER_ID,
					'payments'  => array( 'captures' => array( array( 'status' => 'COMPLETED', 'amount' => self::CAPTURED_AMOUNT ) ) ),
				),
			),
		);
	}

	private function capture_resource( string $paypal_order_id, string $capture_id = self::SAVED_CAPTURE, string $up_href = '' ): array
	{
		$resource = array(
			'id'        => $capture_id,
			'custom_id' => self::WC_ORDER_ID,
			'amount'    => self::CAPTURED_AMOUNT,
		);
		if ( $paypal_order_id ) {
			$resource['supplementary_data'] = array( 'related_ids' => array( 'order_id' => $paypal_order_id ) );
		}
		if ( $up_href ) {
			$resource['links'] = array(
				array( 'rel' => 'self', 'href' => 'https://api.paypal.com/v2/payments/captures/' . $capture_id ),
				array( 'rel' => 'up', 'href' => $up_href ),
			);
		}

		return $resource;
	}

	private function authorization_href( string $authorization_id ): string
	{
		return 'https://api.paypal.com/v2/payments/authorizations/' . $authorization_id;
	}

	private function paypal_order_href( string $paypal_order_id ): string
	{
		return 'https://api.paypal.com/v2/checkout/orders/' . $paypal_order_id;
	}

	private function refund_resource( string $capture_id ): array
	{
		return array(
			'id'        => 'REFUND',
			'custom_id' => self::WC_ORDER_ID,
			'amount'    => array( 'value' => '1.00' ),
			'links'     => array(
				array( 'rel' => 'self', 'href' => 'https://api.paypal.com/v2/payments/refunds/REFUND' ),
				array( 'rel' => 'up', 'href' => 'https://api.paypal.com/v2/payments/captures/' . $capture_id ),
			),
		);
	}

	private function make_request( array $resource ): WP_REST_Request
	{
		$params = array(
			'id'         => 'WH-EVENT',
			'event_type' => 'EVENT',
			'resource'   => $resource,
		);

		/** @var WP_REST_Request&\Mockery\MockInterface $request */
		$request = Mockery::mock( 'WP_REST_Request, ArrayAccess' );
		$request->allows( 'offsetExists' )->andReturnUsing( fn( $key ) => isset( $params[ $key ] ) );
		$request->allows( 'offsetGet' )->andReturnUsing( fn( $key ) => $params[ $key ] ?? null );

		return $request;
	}
}
