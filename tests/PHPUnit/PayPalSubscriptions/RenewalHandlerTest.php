<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayPalSubscriptions;

use ArrayObject;
use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Subscription;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerReceivableBreakdown;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\PayPalSubscriptions\RenewalHandler
 */
class RenewalHandlerTest extends TestCase
{
	private RenewalHandler $sut;

	public function setUp(): void
	{
		parent::setUp();

		$logger    = Mockery::mock( LoggerInterface::class )->shouldIgnoreMissing();
		$this->sut = new RenewalHandler( $logger );
	}

	/**
	 * GIVEN a subscription flagged as already renewing and a sale with a PayPal fee
	 * WHEN the sale is processed
	 * THEN the fee breakdown and transaction fee are stored on the new renewal order
	 * AND the parent order is left untouched
	 */
	public function test_renewal_order_receives_paypal_fee_meta(): void
	{
		$renewal_meta = new ArrayObject();
		$parent_meta  = new ArrayObject();
		$renewal      = $this->make_order( $renewal_meta );
		$parent       = $this->make_order( $parent_meta );
		when( 'wcs_create_renewal_order' )->justReturn( $renewal );
		when( 'wc_get_order' )->justReturn( $parent );
		$breakdown = $this->make_breakdown( true );

		$this->sut->process( array( $this->make_subscription( 'true' ) ), 'SALE-TX-1', $breakdown );

		$this->assertSame( $breakdown->to_array(), $renewal_meta[ PayPalGateway::FEES_META_KEY ] );
		$this->assertSame( '3.18', $renewal_meta['PayPal Transaction Fee'] );
		$this->assertSame( 1, $renewal_meta['saved_meta_count'] ?? 0 );
		$this->assertSame( array(), $parent_meta->getArrayCopy() );
	}

	/**
	 * GIVEN a subscription whose first payment arrives right after it started and a sale with a PayPal fee
	 * WHEN the sale is processed
	 * THEN the fee breakdown and transaction fee are stored on the parent order
	 */
	public function test_parent_order_receives_paypal_fee_meta_for_first_payment(): void
	{
		$parent_meta = new ArrayObject();
		when( 'wc_get_order' )->justReturn( $this->make_order( $parent_meta ) );
		$breakdown = $this->make_breakdown( true );

		$this->sut->process( array( $this->make_subscription( '', time() - 60 ) ), 'SALE-TX-1', $breakdown );

		$this->assertSame( $breakdown->to_array(), $parent_meta[ PayPalGateway::FEES_META_KEY ] );
		$this->assertSame( '3.18', $parent_meta['PayPal Transaction Fee'] );
		$this->assertSame( 1, $parent_meta['saved_meta_count'] ?? 0 );
	}

	/**
	 * GIVEN a sale without a fee breakdown
	 * WHEN it is processed as a renewal or as a first payment
	 * THEN no fee meta is written to the order the payment is booked to
	 *
	 * @dataProvider payment_kind_provider
	 */
	public function test_no_fee_meta_is_written_without_breakdown( string $renewal_flag, int $start_age ): void
	{
		$renewal_meta = new ArrayObject();
		$parent_meta  = new ArrayObject();
		when( 'wcs_create_renewal_order' )->justReturn( $this->make_order( $renewal_meta ) );
		when( 'wc_get_order' )->justReturn( $this->make_order( $parent_meta ) );

		$this->sut->process( array( $this->make_subscription( $renewal_flag, time() - $start_age ) ), 'SALE-TX-1', null );

		$this->assertSame( array(), $renewal_meta->getArrayCopy() );
		$this->assertSame( array(), $parent_meta->getArrayCopy() );
	}

	public function payment_kind_provider(): array
	{
		return array(
			'renewal payment'     => array( 'true', 60 ),
			'first payment'       => array( '', 60 ),
		);
	}

	/**
	 * Builds a subscription stub.
	 *
	 * @param string $renewal_flag Value of the renewal marker meta.
	 * @param int    $start_time   Subscription start timestamp.
	 */
	private function make_subscription( string $renewal_flag, ?int $start_time = null ): WC_Subscription
	{
		$subscription = Mockery::mock( WC_Subscription::class );
		$subscription->allows( 'get_meta' )->with( '_ppcp_is_subscription_renewal' )->andReturn( $renewal_flag );
		$subscription->allows( 'get_time' )->with( 'start' )->andReturn( $start_time ?? time() - 60 );
		$subscription->allows( 'get_id' )->andReturn( 10 );
		$subscription->allows( 'get_parent' )->andReturn( 5 );
		$subscription->allows( 'get_payment_method' )->andReturn( 'ppcp-gateway' );
		$subscription->allows( 'update_status' );
		$subscription->allows( 'update_meta_data' );
		$subscription->allows( 'save_meta_data' );

		return $subscription;
	}

	/**
	 * Builds an order whose written meta ends up in the given store.
	 *
	 * @param ArrayObject $meta Receives updated meta; 'saved_meta_count' counts save_meta_data() calls.
	 */
	private function make_order( ArrayObject $meta ): WC_Order
	{
		$order = Mockery::mock( WC_Order::class );
		$order->allows( 'get_id' )->andReturn( 1 );
		$order->allows( 'set_payment_method' );
		$order->allows( 'payment_complete' );
		$order->allows( 'set_transaction_id' );
		$order->allows( 'save' );
		$order->allows( 'add_order_note' );
		$order->allows( 'update_meta_data' )->andReturnUsing(
			static function ( $key, $value ) use ( $meta ): void {
				$meta[ $key ] = $value;
			}
		);
		$order->allows( 'save_meta_data' )->andReturnUsing(
			static function () use ( $meta ): void {
				$meta['saved_meta_count'] = ( $meta['saved_meta_count'] ?? 0 ) + 1;
			}
		);

		return $order;
	}

	private function make_breakdown( bool $with_fee ): SellerReceivableBreakdown
	{
		return new SellerReceivableBreakdown(
			new Money( 77.0, 'USD' ),
			$with_fee ? new Money( 3.18, 'USD' ) : null,
			null,
			new Money( 73.82, 'USD' ),
			null,
			null,
			array()
		);
	}
}
