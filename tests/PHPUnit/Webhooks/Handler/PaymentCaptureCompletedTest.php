<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WP_REST_Request;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Webhooks\Handler\PaymentCaptureCompleted
 * @covers \WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandlerTrait
 */
class PaymentCaptureCompletedTest extends TestCase
{
	private const WC_ORDER_ID = 42;
	private const PAYPAL_ORDER_ID = 'PAYPAL-ORDER-1';

	private PaymentCaptureCompleted $sut;

	public function setUp(): void
	{
		parent::setUp();

		$logger = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();

		// The transaction ID lookup after a successful capture is not under test here.
		$order_endpoint = Mockery::mock( OrderEndpoint::class );
		$order_endpoint->allows( 'order' )->andThrow( new RuntimeException( 'Not under test.' ) );

		$this->sut = new PaymentCaptureCompleted(
			$logger,
			$order_endpoint
		);
	}

	/**
	 * GIVEN a pending WooCommerce order of 100.00 USD
	 * WHEN PayPal reports a completed capture of 100.00 USD for it
	 * THEN the order is marked as paid
	 * AND the webhook is answered with success
	 */
	public function test_pending_order_is_marked_paid_when_capture_matches_total(): void
	{
		$order = $this->make_order( 'pending', '100.00', 'USD' );
		$order->expects( 'payment_complete' )->once();

		$response = $this->handle( $order, array( 'value' => '100.00', 'currency_code' => 'USD' ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * GIVEN an order that is pending or on hold
	 * WHEN PayPal reports a capture that covers the order total
	 * THEN the order is marked as paid
	 *
	 * @dataProvider covering_capture_provider
	 */
	public function test_order_is_marked_paid_when_capture_covers_total(
		string $status,
		string $order_total,
		string $order_currency,
		array $amount
	): void {
		$order = $this->make_order( $status, $order_total, $order_currency );
		$order->expects( 'payment_complete' )->once();

		$response = $this->handle( $order, $amount );

		$this->assertTrue( $response->get_data()['success'] );
	}

	public function covering_capture_provider(): array
	{
		return array(
			'on-hold order with exact amount'           => array( 'on-hold', '100.00', 'USD', array( 'value' => '100.00', 'currency_code' => 'USD' ) ),
			'capture larger than the total'             => array( 'pending', '100.00', 'USD', array( 'value' => '150.00', 'currency_code' => 'USD' ) ),
			'order total with more decimals than PayPal' => array( 'pending', '900.0000', 'USD', array( 'value' => '900.00', 'currency_code' => 'USD' ) ),
			'currency without decimals'                 => array( 'pending', '1000', 'JPY', array( 'value' => '1000', 'currency_code' => 'JPY' ) ),
		);
	}

	/**
	 * GIVEN a pending order of 100.00 USD
	 * WHEN the webhook capture does not cover the order total in the order currency
	 * THEN the order is not marked as paid
	 * AND a note is added to the order
	 * AND the webhook is answered with a failure
	 *
	 * @dataProvider insufficient_capture_provider
	 *
	 * @param mixed $amount
	 */
	public function test_order_is_not_marked_paid_when_capture_does_not_cover_total( $amount ): void
	{
		$order = $this->make_order( 'pending', '100.00', 'USD' );
		$order->expects( 'add_order_note' )->once();
		$order->expects( 'payment_complete' )->never();

		$response = $this->handle( $order, $amount );

		$this->assertFalse( $response->get_data()['success'] );
	}

	public function insufficient_capture_provider(): array
	{
		return array(
			'much lower amount'            => array( array( 'value' => '0.01', 'currency_code' => 'USD' ) ),
			'one cent less than the total' => array( array( 'value' => '99.99', 'currency_code' => 'USD' ) ),
			'matching value in another currency' => array( array( 'value' => '100.00', 'currency_code' => 'EUR' ) ),
			'missing currency'             => array( array( 'value' => '100.00' ) ),
			'non-string currency'          => array( array( 'value' => '100.00', 'currency_code' => 840 ) ),
			'missing value'                => array( array( 'currency_code' => 'USD' ) ),
			'non-numeric value'            => array( array( 'value' => 'abc', 'currency_code' => 'USD' ) ),
			'amount is not an array'       => array( '100.00 USD' ),
			'amount is missing'            => array( null ),
		);
	}

	/**
	 * GIVEN an order that is already processing
	 * WHEN a capture webhook of only 0.01 USD arrives
	 * THEN the order is left untouched
	 * AND the webhook is answered with success
	 */
	public function test_non_pending_order_is_left_untouched(): void
	{
		$order = $this->make_order( 'processing', '100.00', 'USD' );
		$order->expects( 'add_order_note' )->never();
		$order->expects( 'payment_complete' )->never();

		$response = $this->handle( $order, array( 'value' => '0.01', 'currency_code' => 'USD' ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * @return WC_Order&\Mockery\MockInterface
	 */
	private function make_order( string $status, string $total, string $currency )
	{
		$order = Mockery::mock( WC_Order::class )->shouldIgnoreMissing();
		$order->allows( 'get_id' )->andReturn( self::WC_ORDER_ID );
		$order->allows( 'get_status' )->andReturn( $status );
		$order->allows( 'get_total' )->andReturn( $total );
		$order->allows( 'get_currency' )->andReturn( $currency );
		$order->allows( 'get_meta' )->with( PayPalGateway::ORDER_ID_META_KEY )->andReturn( self::PAYPAL_ORDER_ID );

		return $order;
	}

	/**
	 * @param mixed $amount
	 */
	private function handle( WC_Order $order, $amount ): \WP_REST_Response
	{
		when( 'wc_get_order' )->justReturn( $order );

		$data = array(
			'id'       => 'WEBHOOK-1',
			'resource' => array(
				'id'        => 'CAPTURE-1',
				'custom_id'          => (string) self::WC_ORDER_ID,
				'amount'             => $amount,
				'supplementary_data' => array( 'related_ids' => array( 'order_id' => self::PAYPAL_ORDER_ID ) ),
			),
		);

		/** @var WP_REST_Request&\Mockery\MockInterface $request */
		$request = Mockery::mock( 'WP_REST_Request, ArrayAccess' );
		$request->allows( 'offsetExists' )->andReturnUsing(
			static function ( $key ) use ( $data ): bool {
				return isset( $data[ $key ] );
			}
		);
		$request->allows( 'offsetGet' )->andReturnUsing(
			static function ( $key ) use ( $data ) {
				return $data[ $key ] ?? null;
			}
		);

		return $this->sut->handle_request( $request );
	}
}
