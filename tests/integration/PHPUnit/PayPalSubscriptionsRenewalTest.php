<?php

namespace WooCommerce\PayPalCommerce\Tests\Integration;

use Psr\Log\LoggerInterface;
use WC_Product_Simple;
use WooCommerce\PayPalCommerce\PayPalSubscriptions\RenewalHandler;

/**
 * @group subscriptions
 * @group subscription-paypal
 * @group skip-ci
 */
class PayPalSubscriptionsRenewalTest extends TestCase
{

	/**
	 * Tests that renewal orders are not created for recent subscriptions.
	 *
	 * GIVEN a subscription created 1 minute ago
	 * WHEN the process method is called with this subscription
	 * THEN no renewal order should be created
	 */
	public function test_renewal_order_is_not_created_just_after_receiving_webhook()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		// Simulates receiving webhook 1 minute after subscription start.
		$subscription = $this->createSubscription('-1 minute');

		$handler->process([$subscription], 'TRANSACTION-ID');
		$renewal = $subscription->get_related_orders('ids', array('renewal'));
		$this->assertEquals(0, count($renewal), 'No renewal order should be created for a subscription that is only 1 minute old');
	}

	/**
	 * Tests that renewal orders are created for subscriptions older than 8 hours.
	 *
	 * GIVEN a subscription created 9 hours ago
	 * WHEN the process method is called with this subscription
	 * THEN a renewal order should be created
	 */
	public function test_renewal_order_is_created_when_receiving_webhook_nine_hours_later()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		// Simulates receiving webhook 9 hours after subscription start.
		$subscription = $this->createSubscription('-9 hour');

		$handler->process([$subscription], 'TRANSACTION-ID');
		$renewal = $subscription->get_related_orders('ids', array('renewal'));
		$this->assertEquals(1, count($renewal), 'A renewal order should be created for a subscription that is 9 hours old');
	}

	/**
	 * Tests that renewal orders are created when subscription has renewal meta.
	 *
	 * GIVEN a subscription created 5 minutes ago
	 * AND the subscription has the _ppcp_is_subscription_renewal meta set to 'true'
	 * WHEN the process method is called with this subscription
	 * THEN a renewal order should be created
	 */
	public function test_renewal_order_is_created_when_subscription_has_renewal_meta()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		// Create a subscription that's only 5 minutes old (would normally not trigger renewal)
		$subscription = $this->createSubscription('-5 minute');

		// But mark it as needing renewal
		$subscription->update_meta_data('_ppcp_is_subscription_renewal', 'true');
		$subscription->save_meta_data();

		$handler->process([$subscription], 'TRANSACTION-ID');
		$renewal = $subscription->get_related_orders('ids', array('renewal'));
		$this->assertEquals(1, count($renewal), 'A renewal order should be created when subscription has _ppcp_is_subscription_renewal meta set to true, regardless of age');
	}

	/**
	 * Tests that renewal order payment method matches the subscription.
	 *
	 * GIVEN a subscription created 9 hours ago
	 * AND the subscription has a specific payment method
	 * WHEN the process method is called with this subscription
	 * THEN a renewal order should be created
	 * AND the renewal order should have the same payment method as the subscription
	 */
	public function test_renewal_order_payment_method_matches_subscription()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		$subscription = $this->createSubscription('-9 hour');
		$payment_method = 'ppcp-gateway';
		$subscription->set_payment_method($payment_method);
		$subscription->save();

		$handler->process([$subscription], 'TRANSACTION-ID');
		$renewal_ids = $subscription->get_related_orders('ids', array('renewal'));
		$this->assertEquals(1, count($renewal_ids), 'A renewal order should be created for a subscription that is 9 hours old');

		$renewal_order = wc_get_order(reset($renewal_ids));
		$this->assertEquals($payment_method, $renewal_order->get_payment_method(), 'The renewal order should have the same payment method as the subscription');
	}

	/**
	 * Tests that renewal orders are marked as paid.
	 *
	 * GIVEN a subscription created 9 hours ago
	 * WHEN the process method is called with this subscription
	 * THEN a renewal order should be created
	 * AND the renewal order should be marked as paid
	 */
	public function test_renewal_order_is_marked_as_paid()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		$subscription = $this->createSubscription('-9 hour');

		$handler->process([$subscription], 'TRANSACTION-ID');
		$renewal_ids = $subscription->get_related_orders('ids', array('renewal'));
		$this->assertEquals(1, count($renewal_ids), 'A renewal order should be created for a subscription that is 9 hours old');

		$renewal_order = wc_get_order(reset($renewal_ids));
		$this->assertTrue($renewal_order->is_paid(), 'The renewal order should be marked as paid');
	}

	/**
	 * Tests that transaction ID is set on renewal orders.
	 *
	 * GIVEN a subscription created 9 hours ago
	 * AND a unique transaction ID
	 * WHEN the process method is called with this subscription and transaction ID
	 * THEN a renewal order should be created
	 * AND the renewal order should have the transaction ID set
	 */
	public function test_transaction_id_is_set_on_renewal_order()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		$subscription = $this->createSubscription('-9 hour');
		$transaction_id = 'TEST-TRANSACTION-ID-' . uniqid();

		$handler->process([$subscription], $transaction_id);
		$renewal_ids = $subscription->get_related_orders('ids', array('renewal'));
		$this->assertEquals(1, count($renewal_ids), 'A renewal order should be created for a subscription that is 9 hours old');

		$renewal_order = wc_get_order(reset($renewal_ids));
		$this->assertEquals($transaction_id, $renewal_order->get_transaction_id(), 'The renewal order should have the transaction ID set correctly');
	}

	/**
	 * Tests that subscription status is set to on-hold before renewal.
	 *
	 * GIVEN a subscription created 9 hours ago with 'active' status
	 * WHEN the process method is called with this subscription
	 * THEN the subscription status should be changed to 'on-hold'
	 */
	public function test_subscription_status_is_set_to_on_hold_before_renewal()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		$subscription = $this->createSubscription('-9 hour');
		$initial_status = $subscription->get_status();
		$this->assertEquals('active', $initial_status, 'The subscription should start with active status');

		$handler->process([$subscription], 'TRANSACTION-ID');

		// Status should be on-hold before the renewal order is created
		$this->assertEquals('on-hold', $subscription->get_status(), 'The subscription status should be changed to on-hold before renewal');
	}

	/**
	 * Tests that transaction ID is set on parent order when no renewal is created.
	 *
	 * GIVEN a subscription created 1 minute ago
	 * AND a unique transaction ID
	 * WHEN the process method is called with this subscription and transaction ID
	 * THEN no renewal order should be created
	 * AND the transaction ID should be set on the parent order
	 */
	public function test_transaction_id_is_set_on_parent_order_when_no_renewal()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		$subscription = $this->createSubscription('-1 minute');

		$transaction_id = 'PARENT-TRANSACTION-ID-' . uniqid();
		$parent_order_id = $subscription->get_parent_id();

		$handler->process([$subscription], $transaction_id);

		// No renewal order should be created
		$renewal = $subscription->get_related_orders('ids', array('renewal'));
		$this->assertEquals(0, count($renewal), 'No renewal order should be created for a subscription that is only 1 minute old');

		//use latest order to get the updated status
		$parent_order = wc_get_order($parent_order_id);
		// Transaction ID should be set on parent order
		$this->assertEquals($transaction_id, $parent_order->get_transaction_id(), 'The transaction ID should be set on the parent order when no renewal is created');
	}

	/**
	 * Tests that subscription meta is set when processing parent order.
	 *
	 * GIVEN a subscription created 1 minute ago
	 * AND the subscription has no _ppcp_is_subscription_renewal meta
	 * WHEN the process method is called with this subscription
	 * THEN the _ppcp_is_subscription_renewal meta should be set to 'true'
	 */
	public function test_subscription_meta_is_set_when_processing_parent_order()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		$subscription = $this->createSubscription('-1 minute');

		// Meta should not exist before processing
		$this->assertEmpty($subscription->get_meta('_ppcp_is_subscription_renewal'), 'The subscription should not have _ppcp_is_subscription_renewal meta before processing');

		$handler->process([$subscription], 'TRANSACTION-ID');

		// Meta should be set after processing
		$this->assertEquals('true', $subscription->get_meta('_ppcp_is_subscription_renewal'), 'The _ppcp_is_subscription_renewal meta should be set to true after processing');
	}

	/**
	 * Tests handling subscriptions without valid parent orders.
	 *
	 * GIVEN a subscription created 9 hours ago
	 * AND the parent order is not available
	 * WHEN the process method is called with this subscription
	 * THEN a renewal order should still be created
	 * AND the renewal order should be properly set up with transaction ID
	 * AND the subscription status should be set to 'on-hold'
	 */
	public function test_subscription_without_valid_parent_order()
	{
		$c = $this->getContainer();
		$handler = new RenewalHandler($c->get('woocommerce.logger.woocommerce'));

		$subscription = $this->createSubscription('-9 hour');
		$transaction_id = 'TEST-TRANSACTION-ID-' . uniqid();

		// Simulate a scenario where the parent order doesn't exist or is not a WC_Order
		// Mock wc_get_order to return false instead of a WC_Order instance
		add_filter('woocommerce_get_shop_order_args', function ($args) use ($subscription) {
			if (isset($args['id']) && $args['id'] === $subscription->get_parent_id()) {
				return ['return' => false]; // This causes wc_get_order to return false
			}
			return $args;
		});

		// Process should not throw any errors
		$handler->process([$subscription], $transaction_id);

		// Verify that a renewal order was created (as the subscription is 9 hours old)
		$renewal_ids = $subscription->get_related_orders('ids', array('renewal'));
		$this->assertEquals(1, count($renewal_ids), 'A renewal order should be created even when the parent order is not available');

		// Verify the renewal order was properly set up
		$renewal_order = wc_get_order(reset($renewal_ids));
		$this->assertTrue($renewal_order->is_paid(), 'The renewal order should be marked as paid even when the parent order is not available');
		$this->assertEquals($transaction_id, $renewal_order->get_transaction_id(), 'The renewal order should have the transaction ID set correctly even when the parent order is not available');

		// Verify no errors occurred due to invalid parent order
		$this->assertEquals('on-hold', $subscription->get_status(), 'The subscription status should be set to on-hold even when the parent order is not available');

		// Remove the filter
		remove_all_filters('woocommerce_get_shop_order_args');
	}


	/**
	 * Tests that parent order transaction ID is updated for non-renewal subscriptions.
	 *
	 * GIVEN a subscription created 1 minute ago
	 * AND the parent order has no transaction ID
	 * WHEN the process method is called with this subscription and a unique transaction ID
	 * THEN the parent order's transaction ID should be updated
	 * AND the subscription should be marked for future renewal
	 * AND no renewal order should be created
	 */
	public function test_parent_order_transaction_id_is_updated_when_processing_non_renewal_subscription()
	{
		$c = $this->getContainer();
		$logger = $c->get('woocommerce.logger.woocommerce');
		$handler = new RenewalHandler($logger);

		// Create a subscription that's not ready for renewal
		$subscription = $this->createSubscription('-1 minute');

		// Get the parent order
		$parent_order_id = $subscription->get_parent_id();
		$parent_order = wc_get_order($parent_order_id);

		$this->assertEmpty($parent_order->get_transaction_id(), 'The parent order should not have a transaction ID before processing');

		$transaction_id = 'PARENT-ORDER-TRANSACTION-' . uniqid();
		$handler->process([$subscription], $transaction_id);
		$parent_order = wc_get_order($parent_order_id);

		$this->assertEquals($transaction_id, $parent_order->get_transaction_id(), 'The parent order transaction ID should be updated correctly');

		$this->assertEquals('true', $subscription->get_meta('_ppcp_is_subscription_renewal'), 'The subscription should be marked for future renewal after processing');

		$renewal_orders = $subscription->get_related_orders('ids', array('renewal'));
		$this->assertEquals(0, count($renewal_orders), 'No renewal order should be created for an empty array of subscriptions');
	}

	/**
	 * Tests that the RenewalHandler correctly handles an empty array of subscriptions.
	 *
	 * GIVEN the RenewalHandler with a mocked logger
	 * WHEN the process method is called with an empty array of subscriptions
	 * THEN no exceptions should be thrown
	 * AND the logger should not be called
	 */
	public function test_process_empty_subscriptions_array()
	{
		// Create a logger mock that expects no operations if no subscriptions
		$logger_mock = \Mockery::mock(LoggerInterface::class);
		// The logger should not be called at all with an empty array
		$logger_mock->shouldNotReceive('info');

		$handler = new RenewalHandler($logger_mock);
		$transaction_id = 'TEST-TRANSACTION-EMPTY-ARRAY';

		// Process an empty array of subscriptions
		$handler->process([], $transaction_id);

		// Test is successful if no exceptions are thrown
		// and the mock expectations are met (logger not called)
		$this->assertTrue(true, 'No exceptions were thrown when processing an empty array of subscriptions');
	}

	private function createSubscription(string $startDate)
	{
		$order = wc_create_order([
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
					'product_id' => 42,
					'quantity' => 1
				]
			],
		]);
		// Make sure the order is properly saved
		$order->save();

		$product = new WC_Product_Simple();
		$product->set_props([
			'name' => 'Dummy Product',
			'regular_price' => 10,
			'price' => 10,
			'sku' => 'DUMMY SKU',
			'manage_stock' => false,
			'tax_status' => 'taxable',
			'downloadable' => false,
			'virtual' => false,
			'stock_status' => 'instock',
			'weight' => '1.1',
		]);

		$subscription = wcs_create_subscription([
			'start_date' => gmdate('Y-m-d H:i:s', strtotime($startDate)),
			'order_id' => $order->get_id(),
			'customer_id' => 1,
			'status' => 'active',
			'billing_period' => 'day',
			'billing_interval' => 1,
			'payment_method' => 'ppcp-gateway',
			'line_items' => [
				[
					'product_id' => $product->get_id(),
					'quantity' => 1
				]
			],
		]);

		// Make sure the subscription is properly saved
		$subscription->save();
		return $subscription;
	}
}
