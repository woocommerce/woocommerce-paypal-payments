<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use Mockery;
use WC_Order;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

class OrderStatusHelperTest extends TestCase
{
	public function testPendingStatusIsAwaitingPayment()
	{
		$order = Mockery::mock(WC_Order::class);
		$order->shouldReceive('get_status')->andReturn('pending');

		$helper = new OrderStatusHelper();
		$this->assertTrue($helper->is_awaiting_payment($order));
	}

	public function testOnHoldStatusIsAwaitingPayment()
	{
		$order = Mockery::mock(WC_Order::class);
		$order->shouldReceive('get_status')->andReturn('on-hold');

		$helper = new OrderStatusHelper();
		$this->assertTrue($helper->is_awaiting_payment($order));
	}

	public function testProcessingStatusIsNotAwaitingPayment()
	{
		$order = Mockery::mock(WC_Order::class);
		$order->shouldReceive('get_status')->andReturn('processing');

		$helper = new OrderStatusHelper();
		$this->assertFalse($helper->is_awaiting_payment($order));
	}

	public function testCustomStatusViaFilter()
	{
		$order = Mockery::mock(WC_Order::class);
		$order->shouldReceive('get_status')->andReturn('pending-deposit');

		expect('apply_filters')
			->once()
			->with('woocommerce_paypal_payments_awaiting_payment_statuses', array('pending', 'on-hold'), $order)
			->andReturn(array('pending', 'on-hold', 'pending-deposit'));

		$helper = new OrderStatusHelper();
		$this->assertTrue($helper->is_awaiting_payment($order));
	}

	public function testGetAwaitingPaymentStatusesReturnsDefaults()
	{
		$order = Mockery::mock(WC_Order::class);

		$helper = new OrderStatusHelper();
		$statuses = $helper->get_awaiting_payment_statuses($order);

		$this->assertSame(array('pending', 'on-hold'), $statuses);
	}
}
