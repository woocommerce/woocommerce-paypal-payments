<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions\Helper;

use Mockery;
use WC_Order;
use WC_Subscription;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use function Brain\Monkey\Functions\when;

class SubscriptionHelperTest extends TestCase
{
	public function testPreviousTransaction()
	{
		$subscription = Mockery::mock(WC_Subscription::class);
		$subscription->shouldReceive('get_related_orders')
			->andReturn(
				[
					1 => 1,
					3 => 3,
					2 => 2,
				]
			);

		$wc_order = Mockery::mock(WC_Order::class);
		$wc_order->shouldReceive('get_status')->andReturn('processing');
		$wc_order->shouldReceive('get_transaction_id')->andReturn('ABC123');
		$wc_order->shouldReceive('get_payment_method')->andReturn(CreditCardGateway::ID);

		when('wc_get_order')->justReturn($wc_order);

		$this->assertSame(
			'ABC123',
			(new SubscriptionHelper())->previous_transaction($subscription)
		);
	}
}
