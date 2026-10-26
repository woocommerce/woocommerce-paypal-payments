<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use ArrayObject;
use Mockery;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerReceivableBreakdown;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * @covers \WooCommerce\PayPalCommerce\WcGateway\Processor\FeesMetaTrait
 */
class FeesMetaTraitTest extends TestCase
{
	/**
	 * GIVEN a payment breakdown that includes a PayPal fee
	 * WHEN the fees are stored on the order
	 * THEN the breakdown and the transaction fee are written and persisted
	 */
	public function test_breakdown_with_fee_stores_breakdown_and_transaction_fee(): void
	{
		$meta      = new ArrayObject();
		$breakdown = $this->make_breakdown( new Money( 3.18, 'USD' ) );

		$this->store( $this->make_order( $meta ), $breakdown );

		$this->assertSame(
			array(
				PayPalGateway::FEES_META_KEY => $breakdown->to_array(),
				'PayPal Transaction Fee'     => '3.18',
				'saved'                      => true,
			),
			$meta->getArrayCopy()
		);
	}

	/**
	 * GIVEN a payment breakdown without a PayPal fee
	 * WHEN the fees are stored on the order
	 * THEN only the breakdown is written and persisted
	 */
	public function test_breakdown_without_fee_stores_only_breakdown(): void
	{
		$meta      = new ArrayObject();
		$breakdown = $this->make_breakdown( null );

		$this->store( $this->make_order( $meta ), $breakdown );

		$this->assertSame(
			array(
				PayPalGateway::FEES_META_KEY => $breakdown->to_array(),
				'saved'                      => true,
			),
			$meta->getArrayCopy()
		);
	}

	private function store( WC_Order $order, SellerReceivableBreakdown $breakdown ): void
	{
		$subject = new class() {
			use FeesMetaTrait;

			public function run( WC_Order $order, SellerReceivableBreakdown $breakdown ): void {
				$this->store_paypal_fees( $order, $breakdown );
			}
		};

		$subject->run( $order, $breakdown );
	}

	private function make_order( ArrayObject $meta ): WC_Order
	{
		$order = Mockery::mock( WC_Order::class );
		$order->allows( 'update_meta_data' )->andReturnUsing(
			static function ( $key, $value ) use ( $meta ): void {
				$meta[ $key ] = $value;
			}
		);
		$order->allows( 'save_meta_data' )->andReturnUsing(
			static function () use ( $meta ): void {
				$meta['saved'] = true;
			}
		);

		return $order;
	}

	private function make_breakdown( ?Money $fee ): SellerReceivableBreakdown
	{
		return new SellerReceivableBreakdown(
			new Money( 77.0, 'USD' ),
			$fee,
			null,
			new Money( 73.82, 'USD' ),
			null,
			null,
			array()
		);
	}
}
