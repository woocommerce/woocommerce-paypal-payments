<?php

namespace WooCommerce\PayPalCommerce\Tests\Integration\PayPalSubscriptions;

use WC_Order;
use WC_Product_Simple;
use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;

/**
 * @group subscriptions
 * @group subscription-paypal
 * @covers \WooCommerce\PayPalCommerce\PayPalSubscriptions\PayPalSubscriptionsModule
 */
class PayPalSubscriptionsMetaBoxTest extends TestCase
{
	/**
	 * @var array<int, string>
	 */
	private $captured_log_messages = array();

	/**
	 * @var string|false
	 */
	private $previous_logging_enabled;

	/**
	 * @var string|false
	 */
	private $previous_logging_threshold;

	public function setUp(): void
	{
		parent::setUp();

		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
		require_once ABSPATH . 'wp-admin/includes/screen.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		// WooCommerce only loads its admin helpers when is_admin() is true, which is
		// never the case under CLI. Both this test and the meta box callback under test
		// need wc_get_page_screen_id().
		if (!function_exists('wc_get_page_screen_id')) {
			require_once WP_PLUGIN_DIR . '/woocommerce/includes/admin/wc-admin-functions.php';
		}

		global $wp_meta_boxes;
		$wp_meta_boxes = array();

		$this->previous_logging_enabled = get_option('woocommerce_logs_logging_enabled');
		$this->previous_logging_threshold = get_option('woocommerce_logs_level_threshold');
		update_option('woocommerce_logs_logging_enabled', 'yes');
		update_option('woocommerce_logs_level_threshold', 'debug');

		$this->captured_log_messages = array();
		add_filter('woocommerce_logger_log_message', array($this, 'capture_log_message'), 10, 4);
	}

	public function tearDown(): void
	{
		remove_filter('woocommerce_logger_log_message', array($this, 'capture_log_message'), 10);

		if (false === $this->previous_logging_enabled) {
			delete_option('woocommerce_logs_logging_enabled');
		} else {
			update_option('woocommerce_logs_logging_enabled', $this->previous_logging_enabled);
		}

		if (false === $this->previous_logging_threshold) {
			delete_option('woocommerce_logs_level_threshold');
		} else {
			update_option('woocommerce_logs_level_threshold', $this->previous_logging_threshold);
		}

		parent::tearDown();
	}

	public function capture_log_message($message, $level, $context, $handler)
	{
		$this->captured_log_messages[] = $message;
		return $message;
	}

	/**
	 * GIVEN a plain order or a subscription renewal order viewed on the order edit screen
	 * WHEN the add_meta_boxes action fires for that screen
	 * THEN wcs_get_subscription() must not write a "Subscription could not be loaded." debug entry
	 * AND a control call to wcs_get_subscription() proves the log spy and logging config are active
	 *
	 * @dataProvider order_screen_provider
	 */
	public function test_no_subscription_load_failure_logged_when_viewing_order_screen(string $order_type)
	{
		$order = $this->create_order_for_screen($order_type);
		$order_screen_id = wc_get_page_screen_id('shop_order');

		// Control assertion: prove the spy and logging config actually capture this message.
		wcs_get_subscription($order->get_id());
		$this->assertContains(
			'Subscription could not be loaded.',
			$this->captured_log_messages,
			'Control failed: the log spy did not capture the expected message from a direct wcs_get_subscription() call, so the config or spy is broken.'
		);

		$this->captured_log_messages = array();

		set_current_screen($order_screen_id);
		do_action('add_meta_boxes', $order_screen_id, $order);

		$this->assertNotContains(
			'Subscription could not be loaded.',
			$this->captured_log_messages,
			'Viewing an order screen should not trigger a "Subscription could not be loaded." debug log entry.'
		);
	}

	/**
	 * GIVEN a plain order or a subscription renewal order viewed on the order edit screen
	 * WHEN the add_meta_boxes action fires for that screen
	 * THEN the PayPal Subscription meta box must not be registered on the order screen
	 *
	 * @dataProvider order_screen_provider
	 */
	public function test_no_paypal_subscription_meta_box_registered_on_order_screen(string $order_type)
	{
		$order = $this->create_order_for_screen($order_type);
		$order_screen_id = wc_get_page_screen_id('shop_order');

		set_current_screen($order_screen_id);
		do_action('add_meta_boxes', $order_screen_id, $order);

		$this->assertFalse(
			$this->meta_box_exists($order_screen_id, 'ppcp_paypal_subscription'),
			'The PayPal Subscription meta box should not be registered on an order edit screen.'
		);
	}

	/**
	 * GIVEN a subscription linked to a PayPal subscription via the ppcp_subscription meta
	 * WHEN the add_meta_boxes action fires for the subscription edit screen
	 * THEN the PayPal Subscription meta box should be registered
	 */
	public function test_paypal_subscription_meta_box_registered_on_subscription_screen_when_linked()
	{
		$subscription = $this->createSubscription('-9 hour');
		$subscription->update_meta_data('ppcp_subscription', 'I-TESTSUBSCRIPTION');
		$subscription->save();

		$subscription_screen_id = wc_get_page_screen_id('shop_subscription');

		set_current_screen($subscription_screen_id);
		do_action('add_meta_boxes', $subscription_screen_id, $subscription);

		$this->assertTrue(
			$this->meta_box_exists($subscription_screen_id, 'ppcp_paypal_subscription'),
			'The PayPal Subscription meta box should be registered on the subscription edit screen when the subscription is linked.'
		);
	}

	/**
	 * GIVEN a subscription without a linked PayPal subscription
	 * WHEN the add_meta_boxes action fires for the subscription edit screen
	 * THEN the PayPal Subscription meta box should not be registered
	 */
	public function test_no_meta_box_registered_on_subscription_screen_when_not_linked()
	{
		$subscription = $this->createSubscription('-9 hour');

		$subscription_screen_id = wc_get_page_screen_id('shop_subscription');

		set_current_screen($subscription_screen_id);
		do_action('add_meta_boxes', $subscription_screen_id, $subscription);

		$this->assertFalse(
			$this->meta_box_exists($subscription_screen_id, 'ppcp_paypal_subscription'),
			'The PayPal Subscription meta box should not be registered on the subscription edit screen when the subscription is not linked.'
		);
	}

	public function order_screen_provider(): array
	{
		return array(
			'plain order' => array('plain'),
			'renewal order' => array('renewal'),
		);
	}

	private function create_order_for_screen(string $order_type): WC_Order
	{
		if ('renewal' === $order_type) {
			$subscription = $this->createSubscription('-9 hour');
			// The parent subscription is deliberately linked to a PayPal subscription:
			// a resolver that walks from the renewal order back to its subscription
			// would find this meta and wrongly render the meta box on the order screen.
			$subscription->update_meta_data('ppcp_subscription', 'I-TESTSUBSCRIPTION');
			$subscription->save();

			return wcs_create_renewal_order($subscription);
		}

		$order = wc_create_order(array(
			'customer_id' => 1,
			'set_paid' => true,
			'payment_method' => 'ppcp-gateway',
			'billing' => array(
				'first_name' => 'John',
				'last_name' => 'Doe',
				'address_1' => '969 Market',
				'address_2' => '',
				'city' => 'San Francisco',
				'state' => 'CA',
				'postcode' => '94103',
				'country' => 'US',
				'email' => 'john.doe@example.com',
				'phone' => '(555) 555-5555',
			),
		));
		$order->save();

		return $order;
	}

	private function meta_box_exists(string $screen_id, string $box_id): bool
	{
		global $wp_meta_boxes;

		if (empty($wp_meta_boxes[$screen_id])) {
			return false;
		}

		foreach ($wp_meta_boxes[$screen_id] as $contexts) {
			foreach ($contexts as $priorities) {
				// remove_meta_box() sets the entry to false rather than unsetting it,
				// so a registered box is one whose value is still truthy.
				if (is_array($priorities) && !empty($priorities[$box_id])) {
					return true;
				}
			}
		}

		return false;
	}

	private function createSubscription(string $startDate)
	{
		$order = wc_create_order(array(
			'customer_id' => 1,
			'set_paid' => true,
			'payment_method' => 'ppcp-gateway',
			'billing' => array(
				'first_name' => 'John',
				'last_name' => 'Doe',
				'address_1' => '969 Market',
				'address_2' => '',
				'city' => 'San Francisco',
				'state' => 'CA',
				'postcode' => '94103',
				'country' => 'US',
				'email' => 'john.doe@example.com',
				'phone' => '(555) 555-5555',
			),
			'line_items' => array(
				array(
					'product_id' => 42,
					'quantity' => 1,
				),
			),
		));
		$order->save();

		$product = new WC_Product_Simple();
		$product->set_props(array(
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
		));

		$subscription = wcs_create_subscription(array(
			'start_date' => gmdate('Y-m-d H:i:s', strtotime($startDate)),
			'order_id' => $order->get_id(),
			'customer_id' => 1,
			'status' => 'active',
			'billing_period' => 'day',
			'billing_interval' => 1,
			'payment_method' => 'ppcp-gateway',
			'line_items' => array(
				array(
					'product_id' => $product->get_id(),
					'quantity' => 1,
				),
			),
		));

		$subscription->save();
		return $subscription;
	}
}