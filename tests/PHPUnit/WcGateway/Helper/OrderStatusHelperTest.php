<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use Mockery;
use WC_Order;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

class OrderStatusHelperTest extends TestCase
{
	public function isAwaitingPaymentProvider(): array
	{
		return array(
			'pending'    => array( 'pending', true ),
			'on-hold'    => array( 'on-hold', true ),
			'failed'     => array( 'failed', true ),
			'processing' => array( 'processing', false ),
			'completed'  => array( 'completed', false ),
		);
	}

	/**
	 * @dataProvider isAwaitingPaymentProvider
	 */
	public function testIsAwaitingPayment( string $status, bool $expected )
	{
		$order = Mockery::mock(WC_Order::class);
		$order->shouldReceive('get_status')->andReturn($status);

		$helper = new OrderStatusHelper();
		$this->assertSame($expected, $helper->is_awaiting_payment($order));
	}

	public function testCustomStatusViaFilter()
	{
		$order = Mockery::mock(WC_Order::class);
		$order->shouldReceive('get_status')->andReturn('pending-deposit');

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_awaiting_payment_statuses', array('pending', 'on-hold', 'failed'), $order)
			->andReturn(array('pending', 'on-hold', 'pending-deposit'));

		$helper = new OrderStatusHelper();
		$this->assertTrue($helper->is_awaiting_payment($order));
	}

	public function testGetAwaitingPaymentStatusesReturnsDefaults()
	{
		$order = Mockery::mock(WC_Order::class);

		$helper = new OrderStatusHelper();
		$statuses = $helper->get_awaiting_payment_statuses($order);

		$this->assertSame(array('pending', 'on-hold', 'failed'), $statuses);
	}
}
