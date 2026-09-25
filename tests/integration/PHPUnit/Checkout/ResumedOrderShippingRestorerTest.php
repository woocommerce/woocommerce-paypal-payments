<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Tests\Integration\Checkout;

use Mockery;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WC_Tax;
use WC_Cache_Helper;
use WooCommerce\PayPalCommerce\Tests\Integration\IntegrationMockedTestCase;

/**
 * Integration tests for ResumedOrderShippingRestorer.
 *
 * Reproduces the "shipping line lost on Advanced Card Processing retry" bug:
 * WC_Checkout::create_order() resumes a failed order, wipes its line items,
 * and rebuilds it. set_data_from_cart() restores the shipping TOTAL
 * unconditionally, while create_order_shipping_lines() only restores the
 * shipping LINE ITEM when the previously chosen rate still resolves in the
 * freshly recalculated shipping packages. When it does not, the order is
 * left with a non-zero shipping total and zero shipping line items.
 */
class ResumedOrderShippingRestorerTest extends IntegrationMockedTestCase
{
    /**
     * @var int[]
     */
    private $created_order_ids = [];

    /**
     * @var array<int, array{zone_id: int, instance_id: int}>
     */
    private $created_zones = [];

    /**
     * @var int[]
     */
    private $tax_rate_ids = [];

    /**
     * @var callable|null
     */
    private $throwing_filter;

    /**
     * @var callable|null
     */
    private $flag_callback;

    /**
     * Baseline values of the global WooCommerce tax options, captured in setUp()
     * and restored verbatim in tearDown() so this class never leaks tax
     * configuration into tests that run after it in the same process.
     *
     * @var string|false
     */
    private $original_calc_taxes;

    /**
     * @var string|false
     */
    private $original_tax_based_on;

    /**
     * @var string|false
     */
    private $original_prices_include_tax;

    /**
     * Baseline customer address, captured in setUp() and restored verbatim in
     * tearDown(). fill_cart_and_calculate() overwrites these fields (and persists
     * them via WC_Customer::save()) to drive shipping-zone matching, and nothing
     * else in the flow ever puts them back.
     *
     * @var array<string, string>
     */
    private $original_customer_address = [];

    public function setUp(): void
    {
        parent::setUp();

        $this->original_calc_taxes = get_option('woocommerce_calc_taxes');
        $this->original_tax_based_on = get_option('woocommerce_tax_based_on');
        $this->original_prices_include_tax = get_option('woocommerce_prices_include_tax');

        $customer = $this->customer();
        $this->original_customer_address = [
            'shipping_country' => $customer->get_shipping_country(),
            'shipping_state' => $customer->get_shipping_state(),
            'shipping_city' => $customer->get_shipping_city(),
            'shipping_postcode' => $customer->get_shipping_postcode(),
            'billing_country' => $customer->get_billing_country(),
            'billing_state' => $customer->get_billing_state(),
            'billing_city' => $customer->get_billing_city(),
            'billing_postcode' => $customer->get_billing_postcode(),
        ];
    }

    public function tearDown(): void
    {
        if ($this->throwing_filter) {
            remove_filter('woocommerce_order_get_items', $this->throwing_filter, 10);
            $this->throwing_filter = null;
        }

        if ($this->flag_callback) {
            remove_action('woocommerce_checkout_order_processed', $this->flag_callback, PHP_INT_MAX);
            $this->flag_callback = null;
        }

        foreach ($this->created_order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order instanceof WC_Order) {
                $order->delete(true);
            }
        }
        $this->created_order_ids = [];

        foreach ($this->created_zones as $zone) {
            delete_option('woocommerce_flat_rate_' . $zone['instance_id'] . '_settings');
            WC_Shipping_Zones::delete_zone($zone['zone_id']);
        }
        $this->created_zones = [];

        // WC_Shipping_Zones::delete_zone() does not itself bump the shipping transient
        // version, so cached packages/rates resolved from a zone created by this class
        // would otherwise survive into the next test.
        WC_Cache_Helper::get_transient_version('shipping', true);

        foreach ($this->tax_rate_ids as $tax_rate_id) {
            WC_Tax::_delete_tax_rate($tax_rate_id);
        }
        $this->tax_rate_ids = [];

        // Bump the tax transient version so cached tax-rate lookups from this
        // class's inserted/deleted rates don't leak into later tests.
        WC_Cache_Helper::get_transient_version('taxes', true);

        update_option('woocommerce_calc_taxes', $this->original_calc_taxes);
        update_option('woocommerce_tax_based_on', $this->original_tax_based_on);
        update_option('woocommerce_prices_include_tax', $this->original_prices_include_tax);

        // Restore the customer address fill_cart_and_calculate() overwrote and
        // persisted, so a later test's shipping-zone matching never depends on
        // whatever address this class last left behind.
        if ($this->original_customer_address) {
            $customer = $this->customer();
            $customer->set_shipping_country($this->original_customer_address['shipping_country']);
            $customer->set_shipping_state($this->original_customer_address['shipping_state']);
            $customer->set_shipping_city($this->original_customer_address['shipping_city']);
            $customer->set_shipping_postcode($this->original_customer_address['shipping_postcode']);
            $customer->set_billing_country($this->original_customer_address['billing_country']);
            $customer->set_billing_state($this->original_customer_address['billing_state']);
            $customer->set_billing_city($this->original_customer_address['billing_city']);
            $customer->set_billing_postcode($this->original_customer_address['billing_postcode']);
            $customer->save();
        }

        WC()->session->set('order_awaiting_payment', 0);
        WC()->session->set('chosen_shipping_methods', []);

        // WC_Shipping::calculate_shipping_for_package() caches resolved rates in the
        // session under 'shipping_for_package_<n>', keyed by a package hash. That
        // session is backed by the woocommerce_sessions DB table (WC_Session_Handler),
        // so stale entries survive across processes; clear them explicitly rather than
        // relying solely on the shipping transient bump above.
        if (method_exists(WC()->session, 'get_session_data')) {
            foreach (array_keys(WC()->session->get_session_data()) as $session_key) {
                if (0 === strpos($session_key, 'shipping_for_package_')) {
                    WC()->session->set($session_key, null);
                }
            }
        }

        $this->cart()->empty_cart();

        parent::tearDown();
    }

    /**
     * GIVEN a checkout order with one taxable flat-rate shipping line item
     * WHEN woocommerce_resume_order fires for that order and it is later rebuilt
     * THEN the restorer's recorded snapshot reproduces the item's method_title,
     *      method_id, instance_id, total, total_tax, taxes and tax_status exactly
     *
     * @test
     * @group integration
     * @group checkout
     * @covers \WooCommerce\PayPalCommerce\WcGateway\Helper\ResumedOrderShippingRestorer
     */
    public function it_snapshots_all_shipping_item_fields_when_resume_order_fires(): void
    {
        $this->bootstrapModule();

        $zone = $this->create_shipping_zone_with_flat_rate('18.45', 'taxable');
        $this->enable_taxes_for_country('US');
        $this->fill_cart_and_calculate($zone['rate_key']);

        $order_id = $this->create_initial_order();

        $initial_order = wc_get_order($order_id);
        $initial_items = $initial_order->get_shipping_methods();
        $this->assertCount(1, $initial_items, 'Precondition: the freshly created order must carry one shipping line.');

        $original_item = current($initial_items);
        $this->assertGreaterThan(
            0.0,
            (float) $original_item->get_total_tax(),
            'Precondition: the shipping line must be taxed for this assertion to be meaningful.'
        );

        $expected_method_title = $original_item->get_method_title();
        $expected_method_id = $original_item->get_method_id();
        $expected_instance_id = $original_item->get_instance_id();
        $expected_total = $original_item->get_total();
        $expected_total_tax = $original_item->get_total_tax();
        $expected_taxes = $original_item->get_taxes();
        $expected_tax_status = $original_item->get_tax_status();

        $this->resume_with_missing_rate($order_id);

        do_action('woocommerce_checkout_order_processed', $order_id, $this->posted_data(), wc_get_order($order_id));

        $restored_order = wc_get_order($order_id);
        $restored_items = $restored_order->get_shipping_methods();
        $this->assertCount(1, $restored_items);

        $restored_item = current($restored_items);
        $this->assertSame($expected_method_title, $restored_item->get_method_title());
        $this->assertSame($expected_method_id, $restored_item->get_method_id());
        $this->assertSame($expected_instance_id, $restored_item->get_instance_id());
        $this->assertSame($expected_total, $restored_item->get_total());
        $this->assertSame($expected_total_tax, $restored_item->get_total_tax());
        $this->assertSame($expected_taxes, $restored_item->get_taxes());
        $this->assertSame($expected_tax_status, $restored_item->get_tax_status());
    }

    /**
     * GIVEN a resumed order whose rebuild leaves a shipping total of 18.45 with no shipping line items
     * WHEN woocommerce_checkout_order_processed fires
     * THEN a shipping line matching the snapshotted method_title, method_id, instance_id and total
     *      is added to the order and persisted
     *
     * @test
     * @group integration
     * @group checkout
     * @covers \WooCommerce\PayPalCommerce\WcGateway\Helper\ResumedOrderShippingRestorer
     */
    public function it_restores_the_shipping_line_when_rebuild_leaves_a_total_without_items(): void
    {
        $this->bootstrapModule();

        $zone = $this->create_shipping_zone_with_flat_rate('18.45');
        $this->fill_cart_and_calculate($zone['rate_key']);

        $order_id = $this->create_initial_order();

        $initial_order = wc_get_order($order_id);
        $initial_items = $initial_order->get_shipping_methods();
        $this->assertCount(1, $initial_items, 'Precondition: the initial order must carry the shipping line.');

        $original_item = current($initial_items);
        $expected_method_title = $original_item->get_method_title();
        $expected_method_id = $original_item->get_method_id();
        $expected_instance_id = $original_item->get_instance_id();
        $expected_total = $original_item->get_total();

        $this->resume_with_missing_rate($order_id);

        $bug_state_order = wc_get_order($order_id);
        $this->assertCount(
            0,
            $bug_state_order->get_shipping_methods(),
            'The bug must be reproduced: rebuild must leave no shipping line items.'
        );
        $this->assertEqualsWithDelta(
            18.45,
            (float) $bug_state_order->get_shipping_total(),
            0.001,
            'The bug must be reproduced: shipping total must remain the non-zero cart total.'
        );

        do_action('woocommerce_checkout_order_processed', $order_id, $this->posted_data(), wc_get_order($order_id));

        $restored_order = wc_get_order($order_id);
        $restored_items = $restored_order->get_shipping_methods();
        $this->assertCount(1, $restored_items);

        $restored_item = current($restored_items);
        $this->assertSame($expected_method_title, $restored_item->get_method_title());
        $this->assertSame($expected_method_id, $restored_item->get_method_id());
        $this->assertSame($expected_instance_id, $restored_item->get_instance_id());
        $this->assertSame($expected_total, $restored_item->get_total());
    }

    /**
     * GIVEN a resumed order whose rebuild already restored a shipping line item itself
     * WHEN woocommerce_checkout_order_processed fires
     * THEN the restorer adds nothing and the order's shipping item count is unchanged
     *
     * @test
     * @group integration
     * @group checkout
     * @covers \WooCommerce\PayPalCommerce\WcGateway\Helper\ResumedOrderShippingRestorer
     */
    public function it_adds_nothing_when_the_rebuilt_order_already_has_shipping_items(): void
    {
        $this->bootstrapModule();

        $zone = $this->create_shipping_zone_with_flat_rate('9.99');
        $this->fill_cart_and_calculate($zone['rate_key']);

        $order_id = $this->create_initial_order();

        $this->resume_with_existing_rate($order_id);

        $pre_hook_order = wc_get_order($order_id);
        $pre_hook_items = $pre_hook_order->get_shipping_methods();
        $this->assertCount(
            1,
            $pre_hook_items,
            'Precondition: core must have restored the shipping line itself since the chosen rate still resolves.'
        );
        $pre_hook_item_id = current($pre_hook_items)->get_id();

        do_action('woocommerce_checkout_order_processed', $order_id, $this->posted_data(), wc_get_order($order_id));

        $post_hook_order = wc_get_order($order_id);
        $post_hook_items = $post_hook_order->get_shipping_methods();

        $this->assertCount(1, $post_hook_items, 'The restorer must not add a duplicate shipping line.');
        $this->assertSame($pre_hook_item_id, current($post_hook_items)->get_id());
    }

    /**
     * GIVEN a resumed order whose rebuilt shipping total is 0.00 and has no shipping line items
     * WHEN woocommerce_checkout_order_processed fires
     * THEN the restorer adds nothing
     *
     * @test
     * @group integration
     * @group checkout
     * @covers \WooCommerce\PayPalCommerce\WcGateway\Helper\ResumedOrderShippingRestorer
     */
    public function it_adds_nothing_when_the_rebuilt_shipping_total_is_zero(): void
    {
        $this->bootstrapModule();

        $zone = $this->create_shipping_zone_with_flat_rate('0.00');
        $this->fill_cart_and_calculate($zone['rate_key']);

        $order_id = $this->create_initial_order();

        $this->resume_with_missing_rate($order_id);

        $pre_hook_order = wc_get_order($order_id);
        $this->assertCount(
            0,
            $pre_hook_order->get_shipping_methods(),
            'Precondition: rebuild must leave no shipping line items.'
        );
        $this->assertSame(
            '0.00',
            wc_format_decimal($pre_hook_order->get_shipping_total(), 2),
            'Precondition: the rebuilt shipping total must be zero.'
        );

        do_action('woocommerce_checkout_order_processed', $order_id, $this->posted_data(), wc_get_order($order_id));

        $post_hook_order = wc_get_order($order_id);
        $this->assertCount(0, $post_hook_order->get_shipping_methods());
    }

    /**
     * GIVEN an order that was created without woocommerce_resume_order ever firing for it
     * WHEN woocommerce_checkout_order_processed fires
     * THEN the restorer holds no snapshot for that order and leaves it untouched
     *
     * @test
     * @group integration
     * @group checkout
     * @covers \WooCommerce\PayPalCommerce\WcGateway\Helper\ResumedOrderShippingRestorer
     */
    public function it_takes_no_action_when_no_resume_order_fired(): void
    {
        $this->bootstrapModule();

        $order = new WC_Order();
        $order->set_customer_id($this->customer_id);
        $order->set_status('pending');
        $order->set_shipping_total('12.34');
        $order->save();

        $order_id = $order->get_id();
        $this->created_order_ids[] = $order_id;

        $this->assertCount(
            0,
            wc_get_order($order_id)->get_shipping_methods(),
            'Precondition: the synthetic order must have no shipping line items.'
        );

        do_action('woocommerce_checkout_order_processed', $order_id, $this->posted_data(), wc_get_order($order_id));

        $untouched_order = wc_get_order($order_id);
        $this->assertCount(0, $untouched_order->get_shipping_methods());
        $this->assertSame('12.34', $untouched_order->get_shipping_total());
    }

    /**
     * GIVEN the restore step fails while it inspects/rebuilds the order's shipping items
     * WHEN woocommerce_checkout_order_processed fires
     * THEN the exception is caught, an error naming the order id is logged, and execution
     *      continues past the do_action so process_payment can still run
     *
     * @test
     * @group integration
     * @group checkout
     * @covers \WooCommerce\PayPalCommerce\WcGateway\Helper\ResumedOrderShippingRestorer
     */
    public function it_logs_and_swallows_a_failure_while_persisting_the_restored_item(): void
    {
        $captured_order_id = 0;

        $logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
        $logger->shouldReceive('error')
            ->atLeast()->once()
            ->withArgs(static function (...$args) use (&$captured_order_id) {
                $message = $args[0] ?? '';
                return is_string($message)
                    && $captured_order_id > 0
                    && false !== strpos($message, (string) $captured_order_id);
            });

        // The logger is replaced as an EXTENSION, not as a service: ppcp-wc-gateway's own
        // extensions.php rebuilds 'woocommerce.logger.woocommerce' from scratch and discards
        // whatever the service returned, so a plain service override never reaches the code
        // under test. This module is registered last, so its extension wins.
        $this->bootstrapModule([], [
            'woocommerce.logger.woocommerce' => function () use ($logger) {
                return $logger;
            },
        ]);

        $zone = $this->create_shipping_zone_with_flat_rate('18.45');
        $this->fill_cart_and_calculate($zone['rate_key']);

        $order_id = $this->create_initial_order();
        $captured_order_id = $order_id;

        $this->resume_with_missing_rate($order_id);

        // Real WC_Order::save() swallows its own exceptions internally and never
        // propagates them to the caller, so the failure is injected via the real
        // 'woocommerce_order_get_items' filter (fired from WC_Abstract_Order::get_items(),
        // which get_shipping_methods() calls as part of the restorer's own guard check).
        $this->throwing_filter = static function ($items, $order_arg, $types) use ($order_id) {
            if ((int) $order_arg->get_id() === $order_id && in_array('shipping', (array) $types, true)) {
                throw new \RuntimeException('Simulated failure while restoring the shipping item.');
            }
            return $items;
        };
        add_filter('woocommerce_order_get_items', $this->throwing_filter, 10, 3);

        $flag_reached = false;
        $this->flag_callback = static function () use (&$flag_reached) {
            $flag_reached = true;
        };
        add_action('woocommerce_checkout_order_processed', $this->flag_callback, PHP_INT_MAX, 0);

        do_action('woocommerce_checkout_order_processed', $order_id, $this->posted_data(), wc_get_order($order_id));

        remove_filter('woocommerce_order_get_items', $this->throwing_filter, 10);
        $this->throwing_filter = null;

        $this->assertTrue(
            $flag_reached,
            'Execution must continue past woocommerce_checkout_order_processed despite the persistence failure.'
        );

        $post_hook_order = wc_get_order($order_id);
        $this->assertCount(
            0,
            $post_hook_order->get_shipping_methods(),
            'No shipping line should persist since the restore attempt failed.'
        );
    }

    /**
     * GIVEN a resumed order that the restorer successfully repairs
     * WHEN the restoration completes
     * THEN get_shipping_total() and get_total() are byte-identical to their pre-restoration
     *      values, so the amount sent to PayPal is unaffected
     *
     * @test
     * @group integration
     * @group checkout
     * @covers \WooCommerce\PayPalCommerce\WcGateway\Helper\ResumedOrderShippingRestorer
     */
    public function it_leaves_shipping_total_and_order_total_byte_identical_after_restoring(): void
    {
        $this->bootstrapModule();

        $zone = $this->create_shipping_zone_with_flat_rate('18.45');
        $this->fill_cart_and_calculate($zone['rate_key']);

        $order_id = $this->create_initial_order();

        $this->resume_with_missing_rate($order_id);

        $pre_restore_order = wc_get_order($order_id);
        $this->assertCount(
            0,
            $pre_restore_order->get_shipping_methods(),
            'Precondition: rebuild must leave no shipping line items.'
        );

        // Pin the order total to a value that recalculating from the line items could not
        // reproduce. Without this the assertion below is satisfied by coincidence: a restorer
        // that wrongly called calculate_totals() would arrive at the same numbers anyway, so
        // the test could not tell the two apart. The shipping total is left as the real
        // rebuilt value, so only the grand total carries the sentinel.
        $pre_restore_order->set_total('999.99');
        $pre_restore_order->save();

        $pre_restore_order = wc_get_order($order_id);
        $expected_shipping_total = $pre_restore_order->get_shipping_total();
        $expected_total = $pre_restore_order->get_total();
        $this->assertSame(
            '999.99',
            $expected_total,
            'Precondition: the sentinel total must have persisted.'
        );

        do_action('woocommerce_checkout_order_processed', $order_id, $this->posted_data(), wc_get_order($order_id));

        $restored_order = wc_get_order($order_id);
        $this->assertCount(
            1,
            $restored_order->get_shipping_methods(),
            'Precondition for a meaningful comparison: restoration must have actually happened.'
        );

        $this->assertSame($expected_shipping_total, $restored_order->get_shipping_total());
        $this->assertSame(
            $expected_total,
            $restored_order->get_total(),
            'The restorer must not recalculate the order total.'
        );
    }

    /**
     * Creates a real WC_Shipping_Zone with a flat_rate shipping method instance.
     *
     * @return array{zone_id: int, instance_id: int, rate_key: string}
     */
    private function create_shipping_zone_with_flat_rate(string $cost, string $tax_status = 'none'): array
    {
        $zone = new WC_Shipping_Zone();
        $zone->set_zone_name('PCP Test Zone ' . uniqid());
        $zone->set_zone_order(0);
        $zone->save();
        $zone->set_locations([['code' => 'US', 'type' => 'country']]);
        $zone->save();

        $instance_id = $zone->add_shipping_method('flat_rate');

        update_option(
            'woocommerce_flat_rate_' . $instance_id . '_settings',
            [
                'title' => 'Flat rate',
                'tax_status' => $tax_status,
                'cost' => $cost,
            ]
        );

        WC_Cache_Helper::get_transient_version('shipping', true);

        $this->created_zones[] = [
            'zone_id' => $zone->get_id(),
            'instance_id' => $instance_id,
        ];

        return [
            'zone_id' => $zone->get_id(),
            'instance_id' => $instance_id,
            'rate_key' => 'flat_rate:' . $instance_id,
        ];
    }

    /**
     * Adds the "simple" preset product to the cart and calculates totals with the
     * given rate chosen, so the cart caches a non-zero shipping total.
     */
    private function fill_cart_and_calculate(string $rate_key): void
    {
        $this->cart()->empty_cart();

        $product_id = wc_get_product_id_by_sku('DUMMY_SIMPLE_SKU_01');
        $this->cart()->add_to_cart($product_id, 1);

        $this->customer()->set_shipping_country('US');
        $this->customer()->set_shipping_state('CA');
        $this->customer()->set_shipping_city('Los Angeles');
        $this->customer()->set_shipping_postcode('90001');
        $this->customer()->set_billing_country('US');
        $this->customer()->set_billing_state('CA');
        $this->customer()->set_billing_city('Los Angeles');
        $this->customer()->set_billing_postcode('90001');
        $this->customer()->save();

        WC()->session->set('chosen_shipping_methods', [$rate_key]);

        $this->cart()->calculate_totals();
    }

    /**
     * Enables tax calculation and inserts a real tax rate that also applies to shipping.
     */
    private function enable_taxes_for_country(string $country): void
    {
        update_option('woocommerce_calc_taxes', 'yes');
        update_option('woocommerce_tax_based_on', 'shipping');
        update_option('woocommerce_prices_include_tax', 'no');

        // tax_rate_compound and tax_rate_shipping are integer columns. WC_Tax::prepare_tax_rate()
        // only reformats keys that have a matching format_<key>() method, and there is no
        // format_tax_rate_shipping()/format_tax_rate_compound() in WC_Tax, so a string like
        // 'yes' goes straight into $wpdb->insert() and MySQL silently coerces it to 0. Passing
        // real integers here is required for the shipping flag to actually persist as on.
        $this->tax_rate_ids[] = WC_Tax::_insert_tax_rate([
            'tax_rate_country' => $country,
            'tax_rate_state' => '',
            'tax_rate' => '10.0000',
            'tax_rate_name' => 'PCP Test Tax',
            'tax_rate_priority' => '1',
            'tax_rate_compound' => 0,
            'tax_rate_shipping' => 1,
            'tax_rate_order' => '1',
            'tax_rate_class' => '',
        ]);
    }

    /**
     * The $data array handed to WC_Checkout::create_order(), modelled on
     * WC_Checkout::get_posted_data().
     *
     * @return array<string, mixed>
     */
    private function posted_data(): array
    {
        return [
            'terms' => 1,
            'payment_method' => 'ppcp-credit-card-gateway',
            'order_comments' => '',
            'shipping_method' => '',
            'billing_first_name' => 'John',
            'billing_last_name' => 'Doe',
            'billing_address_1' => '123 Main St',
            'billing_city' => 'Los Angeles',
            'billing_state' => 'CA',
            'billing_postcode' => '90001',
            'billing_country' => 'US',
            'billing_email' => 'customer1@example.com',
            'billing_phone' => '1234567890',
            'shipping_first_name' => 'John',
            'shipping_last_name' => 'Doe',
            'shipping_address_1' => '123 Main St',
            'shipping_city' => 'Los Angeles',
            'shipping_state' => 'CA',
            'shipping_postcode' => '90001',
            'shipping_country' => 'US',
        ];
    }

    /**
     * Creates the first-pass order via the real checkout create_order() flow.
     */
    private function create_initial_order(): int
    {
        $result = WC()->checkout()->create_order($this->posted_data());

        if (is_wp_error($result)) {
            throw new \RuntimeException(
                'create_order() failed on the first pass: ' . $result->get_error_message()
            );
        }

        $order_id = (int) $result;
        $this->created_order_ids[] = $order_id;

        return $order_id;
    }

    /**
     * Marks the order failed and awaiting payment, then resumes checkout with a
     * chosen shipping method key that resolves to no rate in the recalculated
     * packages, without recalculating the cart. This reproduces the reported bug:
     * the cart's cached shipping total survives, but no shipping line item does.
     */
    private function resume_with_missing_rate(int $order_id): void
    {
        $order = wc_get_order($order_id);
        $order->set_status('failed');
        $order->save();

        WC()->session->set('order_awaiting_payment', $order_id);
        WC()->session->set('chosen_shipping_methods', ['flat_rate:999999']);

        $result = WC()->checkout()->create_order($this->posted_data());

        if (is_wp_error($result)) {
            throw new \RuntimeException(
                'create_order() failed while resuming: ' . $result->get_error_message()
            );
        }
    }

    /**
     * Marks the order failed and awaiting payment, then resumes checkout leaving
     * the previously chosen (still valid) shipping method key untouched, so core
     * restores the shipping line item itself.
     */
    private function resume_with_existing_rate(int $order_id): void
    {
        $order = wc_get_order($order_id);
        $order->set_status('failed');
        $order->save();

        WC()->session->set('order_awaiting_payment', $order_id);

        $result = WC()->checkout()->create_order($this->posted_data());

        if (is_wp_error($result)) {
            throw new \RuntimeException(
                'create_order() failed while resuming: ' . $result->get_error_message()
            );
        }
    }
}