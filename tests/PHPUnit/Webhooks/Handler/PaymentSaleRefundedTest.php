<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Mockery;
use Psr\Log\NullLogger;
use WC_Order;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\RefundFeesUpdater;
use WP_REST_Request;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentSaleRefunded
 */
class PaymentSaleRefundedTest extends TestCase
{
	private PaymentSaleRefunded $sut;

	/** @var RefundFeesUpdater&\Mockery\MockInterface */
	private $refund_fees_updater;

	public function setUp(): void
	{
		parent::setUp();

		$this->refund_fees_updater = Mockery::mock( RefundFeesUpdater::class );
		$this->sut                 = new PaymentSaleRefunded( new NullLogger(), $this->refund_fees_updater );
	}

	/**
	 * @scenario When handle_request is called with a refund_id that is already present in get_refunds_meta() for the matching order, wc_create_refund is NOT called and the handler returns a 2xx response.
	 */
	public function test_wc_create_refund_is_not_called_when_refund_id_already_in_meta(): void
	{
		// Arrange
		$refund_id = 'REFUND-ALREADY-PROCESSED';
		$wc_order  = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'get_meta' )
			->with( PayPalGateway::REFUNDS_META_KEY )
			->andReturn( array( $refund_id ) );

		when( 'wc_get_orders' )->justReturn( array( $wc_order ) );
		expect( 'wc_create_refund' )->never();

		// When
		$response = $this->sut->handle_request( $this->make_request( $refund_id ) );

		// Then
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @scenario When handle_request is called with a refund_id that is NOT present in get_refunds_meta() for the matching order, wc_create_refund IS called exactly once per matching order.
	 */
	public function test_wc_create_refund_is_called_once_when_refund_id_not_in_meta(): void
	{
		// Arrange
		$refund_id   = 'REFUND-NEW';
		$refund_mock = new \stdClass();
		$wc_order    = $this->make_processable_order_mock( 1, array() );

		when( 'wc_get_orders' )->justReturn( array( $wc_order ) );
		expect( 'wc_create_refund' )->once()->andReturn( $refund_mock );
		$this->refund_fees_updater->allows( 'update' );

		// When
		$response = $this->sut->handle_request( $this->make_request( $refund_id ) );

		// Then
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @scenario When the same webhook payload is delivered N times (N > 1) for an already-processed refund_id, the total refunded amount on the order equals the amount of a single refund, not N times that amount.
	 */
	public function test_wc_create_refund_is_called_only_once_across_repeated_deliveries(): void
	{
		// Arrange — stateful order mock: meta starts empty, gets populated after first call
		$refund_id   = 'REFUND-REPEATED';
		$refund_mock = new \stdClass();
		$order_meta  = array();

		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'get_id' )->andReturn( 1 );
		$wc_order->allows( 'get_meta' )
			->with( PayPalGateway::REFUNDS_META_KEY )
			->andReturnUsing( function () use ( &$order_meta ) {
				return $order_meta;
			} );
		$wc_order->allows( 'update_meta_data' )
			->andReturnUsing( function ( $key, $value ) use ( &$order_meta ) {
				if ( $key === PayPalGateway::REFUNDS_META_KEY ) {
					$order_meta = $value;
				}
			} );
		$wc_order->allows( 'save' );
		$wc_order->allows( 'add_order_note' );
		$wc_order->allows( 'set_transaction_id' );

		when( 'wc_get_orders' )->justReturn( array( $wc_order ) );
		expect( 'wc_create_refund' )->once()->andReturn( $refund_mock );
		$this->refund_fees_updater->allows( 'update' );

		$request = $this->make_request( $refund_id );

		// When — first delivery creates the refund and records refund_id in meta
		$this->sut->handle_request( $request );

		// When — second delivery of the same event finds refund_id already in meta
		$response = $this->sut->handle_request( $request );

		// Then — second call returns 200 and Brain\Monkey verifies wc_create_refund was called exactly once
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @scenario When get_refunds_meta() returns an empty or null value for a matching order, handle_request treats this as no prior refund recorded and proceeds to call wc_create_refund normally.
	 */
	public function test_wc_create_refund_is_called_when_meta_returns_null(): void
	{
		// Arrange — get_meta returns null (non-array), RefundMetaTrait coerces it to []
		$refund_id   = 'REFUND-NULL-META';
		$refund_mock = new \stdClass();
		$wc_order    = $this->make_processable_order_mock( 2, null );

		when( 'wc_get_orders' )->justReturn( array( $wc_order ) );
		expect( 'wc_create_refund' )->once()->andReturn( $refund_mock );
		$this->refund_fees_updater->allows( 'update' );

		// When
		$response = $this->sut->handle_request( $this->make_request( $refund_id ) );

		// Then
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Builds a WP_REST_Request mock carrying a minimal PAYMENT.SALE.REFUNDED payload.
	 *
	 * @param string $refund_id PayPal refund ID to embed in the resource.
	 * @return WP_REST_Request
	 */
	private function make_request( string $refund_id ): WP_REST_Request
	{
		$resource = array(
			'id'                    => $refund_id,
			'sale_id'               => 'SALE-TX-001',
			'total_refunded_amount' => array( 'value' => '10.00' ),
		);

		/** @var WP_REST_Request&\Mockery\MockInterface $request */
		$request = Mockery::mock( 'WP_REST_Request, ArrayAccess' );
		$request->allows( 'offsetExists' )->andReturn( true );
		$request->allows( 'offsetGet' )->with( 'resource' )->andReturn( $resource );

		return $request;
	}

	/**
	 * Builds a WC_Order mock wired for the full refund-creation code path.
	 *
	 * @param int        $id         Order ID returned by get_id().
	 * @param array|null $meta_value Value returned by get_meta(REFUNDS_META_KEY). null simulates a missing/non-array meta entry.
	 * @return WC_Order
	 */
	private function make_processable_order_mock( int $id, $meta_value ): WC_Order
	{
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'get_id' )->andReturn( $id );
		$wc_order->allows( 'get_meta' )
			->with( PayPalGateway::REFUNDS_META_KEY )
			->andReturn( $meta_value );
		$wc_order->allows( 'update_meta_data' );
		$wc_order->allows( 'save' );
		$wc_order->allows( 'add_order_note' );
		$wc_order->allows( 'set_transaction_id' );

		return $wc_order;
	}
}
