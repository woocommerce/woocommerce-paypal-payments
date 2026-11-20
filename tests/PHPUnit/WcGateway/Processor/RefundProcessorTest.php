<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentsEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Capture;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Payments;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Entity\RefundCapture;
use WooCommerce\PayPalCommerce\ApiClient\Helper\InvoicePrefixGetter;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\RefundFeesUpdater;
use function Brain\Monkey\Filters\expectApplied;

/**
 * @covers \WooCommerce\PayPalCommerce\WcGateway\Processor\RefundProcessor
 */
class RefundProcessorTest extends TestCase
{
	private const REFUND_ID = 'REFUND-1';

	/**
	 * The refund that the processor handed to PayPal.
	 *
	 * @var RefundCapture|null
	 */
	private $sent_refund;

	private RefundProcessor $sut;

	public function setUp(): void
	{
		parent::setUp();

		$this->sent_refund = null;

		$payments_endpoint = Mockery::mock( PaymentsEndpoint::class );
		$payments_endpoint->allows( 'refund' )->andReturnUsing(
			function ( RefundCapture $refund ): string {
				$this->sent_refund = $refund;
				return self::REFUND_ID;
			}
		);

		$prefix = Mockery::mock( InvoicePrefixGetter::class );
		$prefix->allows( 'get' )->andReturn( 'PRE-' );

		$this->sut = new RefundProcessor(
			Mockery::mock( OrderEndpoint::class ),
			$payments_endpoint,
			Mockery::mock( RefundFeesUpdater::class ),
			$prefix,
			Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing()
		);
	}

	/**
	 * GIVEN a WooCommerce order of 400.00 AED that was captured in PayPal
	 * WHEN 40.00 AED is refunded and nothing changes the refund money
	 * THEN PayPal receives a refund of 40.00 AED
	 * AND the PayPal refund ID is returned
	 */
	public function test_refund_is_sent_in_order_currency_by_default(): void
	{
		$refund_id = $this->sut->refund( $this->make_paypal_order(), $this->make_wc_order( 'AED' ), 40.0 );

		$this->assertSame( self::REFUND_ID, $refund_id );
		$this->assertSame( 'AED', $this->sent_refund->amount()->currency_code() );
		$this->assertSame( '40.00', $this->sent_refund->amount()->value_str() );
	}

	/**
	 * GIVEN a WooCommerce order in AED that was captured in USD
	 * WHEN a multi-currency plugin returns 25.00 USD as the refund money
	 * THEN PayPal receives a refund of 25.00 USD
	 */
	public function test_refund_is_sent_in_the_money_returned_by_the_filter(): void
	{
		expectApplied( 'woocommerce_paypal_payments_refund_money' )->andReturn( new Money( 25.0, 'USD' ) );

		$this->sut->refund( $this->make_paypal_order(), $this->make_wc_order( 'AED' ), 100.0 );

		$this->assertSame( 'USD', $this->sent_refund->amount()->currency_code() );
		$this->assertSame( '25.00', $this->sent_refund->amount()->value_str() );
	}

	/**
	 * GIVEN a WooCommerce order of 400.00 AED that was captured in PayPal
	 * WHEN a third-party callback returns a value that is not money
	 * THEN PayPal receives the refund in the order currency and amount
	 *
	 * @dataProvider invalid_filter_value_provider
	 *
	 * @param mixed $value
	 */
	public function test_refund_ignores_a_filter_value_that_is_not_money( $value ): void
	{
		expectApplied( 'woocommerce_paypal_payments_refund_money' )->andReturn( $value );

		$this->sut->refund( $this->make_paypal_order(), $this->make_wc_order( 'AED' ), 40.0 );

		$this->assertSame( 'AED', $this->sent_refund->amount()->currency_code() );
		$this->assertSame( '40.00', $this->sent_refund->amount()->value_str() );
	}

	public function invalid_filter_value_provider(): array
	{
		return array(
			'null'        => array( null ),
			'a float'     => array( 25.0 ),
			'an array'    => array( array( 'currency_code' => 'USD', 'value' => '25.00' ) ),
			'plain object' => array( new \stdClass() ),
		);
	}

	/**
	 * GIVEN a WooCommerce order of 400.00 AED that was captured in PayPal
	 * WHEN 40.00 AED is refunded
	 * THEN third-party code can read the refund as 40.00 AED money, the WC order, the refunded capture and the PayPal order
	 */
	public function test_refund_filter_receives_refund_money_order_capture_and_paypal_order(): void
	{
		$received = array();
		expectApplied( 'woocommerce_paypal_payments_refund_money' )
			->once()
			->andReturnUsing(
				static function ( ...$args ) use ( &$received ) {
					$received = $args;
					return $args[0];
				}
			);
		$wc_order     = $this->make_wc_order( 'AED' );
		$paypal_order = $this->make_paypal_order();

		$this->sut->refund( $paypal_order, $wc_order, 40.0 );

		$this->assertCount( 4, $received );
		[ $money, $filtered_wc_order, $capture, $filtered_paypal_order ] = $received;
		$this->assertInstanceOf( Money::class, $money );
		$this->assertSame( 'AED', $money->currency_code() );
		$this->assertSame( 40.0, $money->value() );
		$this->assertSame( $wc_order, $filtered_wc_order );
		$this->assertSame( $paypal_order->purchase_units()[0]->payments()->captures()[0], $capture );
		$this->assertSame( $paypal_order, $filtered_paypal_order );
	}

	private function make_wc_order( string $currency ): WC_Order
	{
		$wc_order = Mockery::mock( WC_Order::class );
		$wc_order->allows( 'get_currency' )->andReturn( $currency );
		$wc_order->allows( 'get_order_number' )->andReturn( '42' );

		return $wc_order;
	}

	private function make_paypal_order(): Order
	{
		$capture = Mockery::mock( Capture::class );
		$capture->allows( 'invoice_id' )->andReturn( '' );

		$payments = Mockery::mock( Payments::class );
		$payments->allows( 'captures' )->andReturn( array( $capture ) );

		$purchase_unit = Mockery::mock( PurchaseUnit::class );
		$purchase_unit->allows( 'payments' )->andReturn( $payments );

		$order = Mockery::mock( Order::class );
		$order->allows( 'purchase_units' )->andReturn( array( $purchase_unit ) );

		return $order;
	}
}
