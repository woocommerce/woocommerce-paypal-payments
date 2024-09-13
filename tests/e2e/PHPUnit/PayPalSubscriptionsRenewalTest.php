<?php

namespace WooCommerce\PayPalCommerce\Tests\E2e;

use WC_Subscriptions_Product;
use WooCommerce\PayPalCommerce\PayPalSubscriptions\RenewalHandler;

class PayPalSubscriptionsRenewalTest extends TestCase
{
	public function test_process()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		$order = wc_create_order();
		$order->set_customer_id( 1 );
		$order->save();

		$subscription = wcs_create_subscription(
			array(
				'order_id'         => $order->get_id(),
				'status'           => 'active',
				'billing_period'   => WC_Subscriptions_Product::get_period( $_ENV['PAYPAL_SUBSCRIPTIONS_PRODUCT_ID'] ),
				'billing_interval' => WC_Subscriptions_Product::get_interval( $_ENV['PAYPAL_SUBSCRIPTIONS_PRODUCT_ID'] ),
				'customer_id'      => $order->get_customer_id(),
			)
		);

		$parent = $subscription->get_related_orders( 'ids', array( 'parent' ) );
		$this->assertEquals(count($parent), 1);
		$renewal = $subscription->get_related_orders( 'ids', array( 'renewal' ) );
		$this->assertEquals(count($renewal), 0);

		$handler->process([$subscription], 'TRANSACTION-ID');
		$renewal = $subscription->get_related_orders( 'ids', array( 'renewal' ) );
		$this->assertEquals(count($renewal), 0);

		$handler->process([$subscription], 'TRANSACTION-ID');
		$renewal = $subscription->get_related_orders( 'ids', array( 'renewal' ) );
		$this->assertEquals(count($renewal), 1);
	}
}
