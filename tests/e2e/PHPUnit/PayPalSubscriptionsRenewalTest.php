<?php

namespace WooCommerce\PayPalCommerce\Tests\E2e;

use WooCommerce\PayPalCommerce\PayPalSubscriptions\RenewalHandler;

class PayPalSubscriptionsRenewalTest extends TestCase
{
	public function test_parent_order()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		// Simulates receiving webhook 1 minute after subscription start.
		$subscription = $this->createSubscription('-1 minute');

		$handler->process([$subscription], 'TRANSACTION-ID');
		$renewal = $subscription->get_related_orders( 'ids', array( 'renewal' ) );
		$this->assertEquals(count($renewal), 0);
	}

	public function test_renewal_order()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		// Simulates receiving webhook 9 hours after subscription start.
		$subscription = $this->createSubscription('-9 hour');

		$handler->process([$subscription], 'TRANSACTION-ID');
		$renewal = $subscription->get_related_orders( 'ids', array( 'renewal' ) );
		$this->assertEquals(count($renewal), 1);
	}

	private function createSubscription(string $startDate)
	{
		$args = [
			'method' => 'POST',
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( 'admin:admin' ),
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode([
				'customer_id' => 1,
				'set_paid' => true,
				'payment_method' => 'ppcp-gateway',
				'billing' => [
					'first_name' => 'John',
					'last_name' => 'Doe',
					'address_1' => '969 Market',
					'address_2' => '',
					'city' => 'San Francisco',
					'state' => 'CA',
					'postcode' => '94103',
					'country' => 'US',
					'email' => 'john.doe@example.com',
					'phone' => '(555) 555-5555'
				],
				'line_items' => [
					[
						'product_id' => 156,
						'quantity' => 1
					]
				],
			]),
		];

		$response = wp_remote_request(
			'https://woocommerce-paypal-payments.ddev.site/wp-json/wc/v3/orders',
			$args
		);

		$body = json_decode( $response['body'] );

		$args = [
			'method' => 'POST',
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( 'admin:admin' ),
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode([
				'start_date' => gmdate( 'Y-m-d H:i:s', strtotime($startDate) ),
				'parent_id' => $body->id,
				'customer_id' => 1,
				'status' => 'active',
				'billing_period' => 'day',
				'billing_interval' => 1,
				'payment_method' => 'ppcp-gateway',
				'line_items' => [
					[
						'product_id' => $_ENV['PAYPAL_SUBSCRIPTIONS_PRODUCT_ID'],
						'quantity' => 1
					]
				],
			]),
		];

		$response = wp_remote_request(
			'https://woocommerce-paypal-payments.ddev.site/wp-json/wc/v3/subscriptions?per_page=1',
			$args
		);

		$body = json_decode( $response['body'] );

		return wcs_get_subscription($body->id);
	}
}
