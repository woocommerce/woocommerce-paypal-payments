<?php

namespace WooCommerce\PayPalCommerce\Tests\E2e;

use WooCommerce\PayPalCommerce\PayPalSubscriptions\RenewalHandler;

class PayPalSubscriptionsRenewalTest extends TestCase
{
	public function test_is_renewal_by_meta()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		$subscription = $this->createSubscription();

		$handler->process([$subscription], 'TRANSACTION-ID');
		$renewal = $subscription->get_related_orders( 'ids', array( 'renewal' ) );
		$this->assertEquals(count($renewal), 0);

		$handler->process([$subscription], 'TRANSACTION-ID');
		$renewal = $subscription->get_related_orders( 'ids', array( 'renewal' ) );
		$this->assertEquals(count($renewal), 1);
	}

	private function createSubscription()
	{
		$order = wc_create_order();
		$order->set_customer_id(1);
		$order->save();

		return wcs_create_subscription(
			array(
				'order_id' => $order->get_id(),
				'status' => 'active',
				'billing_period' => 'day',
				'billing_interval' => '1',
				'customer_id' => $order->get_customer_id(),
			)
		);
	}
}
