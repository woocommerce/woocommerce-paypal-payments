<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WP_REST_Request;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Webhooks\Handler\CheckoutOrderCompleted
 * @covers \WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandlerTrait
 */
class CheckoutOrderCompletedTest extends TestCase
{
	private const PAYPAL_ORDER_ID = 'PAYPAL-ORDER-1';

	private CheckoutOrderCompleted $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->sut = new CheckoutOrderCompleted(
			Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing()
		);
	}

	/**
	 * GIVEN a WooCommerce order of 100.00 USD
	 * WHEN PayPal reports the order completed with a COMPLETED capture covering the total in its purchase unit
	 * THEN the order is marked as paid without a warning note
	 * AND the webhook is answered with success
	 *
	 * @dataProvider covering_capture_provider
	 */
	public function test_order_is_marked_paid_when_completed_capture_covers_total(
		string $order_total,
		string $order_currency,
		array $amount
	): void {
		$order = $this->make_order( 42, $order_total, $order_currency );
		$order->expects( 'payment_complete' )->once();
		$order->expects( 'add_order_note' )->never();

		$response = $this->handle(
			array( $order ),
			array( $this->purchase_unit( '42', array( $this->capture( 'COMPLETED', $amount ) ) ) )
		);

		$this->assertTrue( $response->get_data()['success'] );
	}

	public function covering_capture_provider(): array
	{
		return array(
			'exact amount'                      => array( '100.00', 'USD', array( 'value' => '100.00', 'currency_code' => 'USD' ) ),
			'capture larger than the total'     => array( '100.00', 'USD', array( 'value' => '120.00', 'currency_code' => 'USD' ) ),
			'order total with more decimals'    => array( '900.0000', 'USD', array( 'value' => '900.00', 'currency_code' => 'USD' ) ),
			'currency without decimals'         => array( '1000', 'JPY', array( 'value' => '1000', 'currency_code' => 'JPY' ) ),
		);
	}

	/**
	 * GIVEN a WooCommerce order of 100.00 USD
	 * WHEN PayPal reports the order completed but its purchase unit holds no completed capture of the total
	 * THEN the order is not marked as paid
	 * AND a note naming the PayPal order is added to the WooCommerce order
	 * AND the webhook is still answered with success
	 *
	 * @dataProvider unpaid_purchase_unit_provider
	 */
	public function test_order_is_not_marked_paid_without_completed_capture_of_total( array $purchase_unit ): void
	{
		$order = $this->make_order( 42, '100.00', 'USD' );
		$order->expects( 'payment_complete' )->never();
		$order->expects( 'add_order_note' )
			->once()
			->with(
				Mockery::on(
					static function ( $note ): bool {
						return strpos( (string) $note, self::PAYPAL_ORDER_ID ) !== false;
					}
				)
			);

		$response = $this->handle( array( $order ), array( $purchase_unit ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	public function unpaid_purchase_unit_provider(): array
	{
		$full   = array( 'value' => '100.00', 'currency_code' => 'USD' );
		$cheaper = array( 'value' => '99.99', 'currency_code' => 'USD' );

		return array(
			'capture of a lower amount'         => array( $this->purchase_unit( '42', array( $this->capture( 'COMPLETED', array( 'value' => '1.00', 'currency_code' => 'USD' ) ) ) ) ),
			'capture one cent short'            => array( $this->purchase_unit( '42', array( $this->capture( 'COMPLETED', $cheaper ) ) ) ),
			'capture in another currency'       => array( $this->purchase_unit( '42', array( $this->capture( 'COMPLETED', array( 'value' => '100.00', 'currency_code' => 'EUR' ) ) ) ) ),
			'capture without currency'          => array( $this->purchase_unit( '42', array( $this->capture( 'COMPLETED', array( 'value' => '100.00' ) ) ) ) ),
			'capture without amount'            => array( $this->purchase_unit( '42', array( array( 'status' => 'COMPLETED' ) ) ) ),
			'pending capture of the total'      => array( $this->purchase_unit( '42', array( $this->capture( 'PENDING', $full ) ) ) ),
			'declined capture of the total'     => array( $this->purchase_unit( '42', array( $this->capture( 'DECLINED', $full ) ) ) ),
			'authorization only'                => array(
				array(
					'custom_id' => '42',
					'payments'  => array( 'authorizations' => array( array( 'status' => 'CREATED', 'amount' => $full ) ) ),
				),
			),
			'no payments at all'                => array( array( 'custom_id' => '42' ) ),
			'covering capture of another purchase unit' => array( $this->purchase_unit( '99', array( $this->capture( 'COMPLETED', $full ) ) ) ),
		);
	}

	/**
	 * GIVEN a WooCommerce order that no longer needs payment (already authorized, paid or refunded)
	 * WHEN PayPal reports the order completed but its purchase unit holds no completed capture of the total
	 * THEN the order is neither marked as paid nor annotated
	 * AND the webhook is still answered with success
	 *
	 * @dataProvider order_not_needing_payment_provider
	 */
	public function test_order_not_needing_payment_is_skipped_silently_without_completed_capture( array $purchase_unit ): void
	{
		$order = $this->make_order( 42, '100.00', 'USD', 'ppcp-gateway', false );
		$order->expects( 'payment_complete' )->never();
		$order->expects( 'add_order_note' )->never();

		$response = $this->handle( array( $order ), array( $purchase_unit ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	public function order_not_needing_payment_provider(): array
	{
		$full = array( 'value' => '100.00', 'currency_code' => 'USD' );

		return array(
			'on-hold order with authorization only' => array(
				array(
					'custom_id' => '42',
					'payments'  => array( 'authorizations' => array( array( 'status' => 'CREATED', 'amount' => $full ) ) ),
				),
			),
			'processing order with refunded capture' => array( $this->purchase_unit( '42', array( $this->capture( 'REFUNDED', $full ) ) ) ),
		);
	}

	/**
	 * GIVEN two WooCommerce orders in one PayPal order, each with its own purchase unit
	 * WHEN only the purchase unit of the first order holds a completed capture of its total
	 * THEN only the first order is marked as paid
	 * AND the second order receives a note instead
	 */
	public function test_only_the_fully_captured_order_is_marked_paid(): void
	{
		$paid = $this->make_order( 42, '100.00', 'USD' );
		$paid->expects( 'payment_complete' )->once();
		$paid->expects( 'add_order_note' )->never();

		$unpaid = $this->make_order( 43, '50.00', 'USD' );
		$unpaid->expects( 'payment_complete' )->never();
		$unpaid->expects( 'add_order_note' )->once();

		$response = $this->handle(
			array( $paid, $unpaid ),
			array(
				$this->purchase_unit( '42', array( $this->capture( 'COMPLETED', array( 'value' => '100.00', 'currency_code' => 'USD' ) ) ) ),
				$this->purchase_unit( '43', array( $this->capture( 'PENDING', array( 'value' => '50.00', 'currency_code' => 'USD' ) ) ) ),
			)
		);

		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * GIVEN a WooCommerce order paid with Pay upon Invoice
	 * WHEN the PayPal order completed webhook arrives
	 * THEN the order is skipped and neither marked as paid nor annotated
	 */
	public function test_pay_upon_invoice_order_is_skipped(): void
	{
		$order = $this->make_order( 42, '100.00', 'USD', PayUponInvoiceGateway::ID );
		$order->expects( 'payment_complete' )->never();
		$order->expects( 'add_order_note' )->never();

		$response = $this->handle( array( $order ), array( $this->purchase_unit( '42', array() ) ) );

		$this->assertTrue( $response->get_data()['success'] );
	}

	/**
	 * @return WC_Order&\Mockery\MockInterface
	 */
	private function make_order(
		int $id,
		string $total,
		string $currency,
		string $payment_method = 'ppcp-gateway',
		bool $needs_payment = true
	) {
		$order = Mockery::mock( WC_Order::class )->shouldIgnoreMissing();
		$order->allows( 'needs_payment' )->andReturn( $needs_payment );
		$order->allows( 'get_id' )->andReturn( $id );
		$order->allows( 'get_total' )->andReturn( $total );
		$order->allows( 'get_currency' )->andReturn( $currency );
		$order->allows( 'get_payment_method' )->andReturn( $payment_method );
		$order->allows( 'get_meta' )->with( PayPalGateway::ORDER_ID_META_KEY )->andReturn( self::PAYPAL_ORDER_ID );

		return $order;
	}

	private function purchase_unit( string $custom_id, array $captures ): array
	{
		return array(
			'custom_id' => $custom_id,
			'payments'  => array( 'captures' => $captures ),
		);
	}

	private function capture( string $status, array $amount ): array
	{
		return array(
			'status' => $status,
			'amount' => $amount,
		);
	}

	/**
	 * @param WC_Order[] $orders
	 */
	private function handle( array $orders, array $purchase_units ): \WP_REST_Response
	{
		when( 'wc_get_orders' )->justReturn( $orders );

		$data = array(
			'id'       => 'WEBHOOK-1',
			'resource' => array(
				'id'             => self::PAYPAL_ORDER_ID,
				'purchase_units' => $purchase_units,
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
