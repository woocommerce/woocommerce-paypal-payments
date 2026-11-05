<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Axo;

use Brain\Monkey\Actions;
use Mockery;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Axo\Assets\AxoManager;
use WooCommerce\PayPalCommerce\Axo\Service\AxoApplies;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\when;

class AxoModuleInsightsScriptTest extends TestCase
{
    private const SCRIPT_URL = 'https://example.test/paypal-insights.js';

    private const HANDLE = 'wc-ppcp-paypal-insights-library';

    /**
     * GIVEN a visitor on a page, with Fastlane on or off
     * WHEN the page enqueues its scripts
     * THEN the Insights library is enqueued only where tracking applies
     * AND it loads async from the configured URL
     *
     * @dataProvider insights_enqueue_provider
     */
    public function test_insights_library_is_enqueued_only_where_fastlane_tracking_applies(
        bool $use_fastlane,
        bool $is_checkout,
        bool $is_logged_in,
        string $endpoint,
        bool $expect_enqueued
    ): void {
        unset($GLOBALS['wp']);

        when('is_checkout')->justReturn($is_checkout);
        when('is_user_logged_in')->justReturn($is_logged_in);
        when('is_wc_endpoint_url')->alias(
            static fn($queried = '') => '' !== $endpoint && $endpoint === $queried
        );

        $enqueued = array();
        when('wp_enqueue_script')->alias(
            static function (...$args) use (&$enqueued): void {
                $enqueued[] = $args;
            }
        );

        $callbacks = $this->capture_enqueue_scripts_callbacks($use_fastlane);
        foreach ($callbacks as $callback) {
            $callback();
        }

        $expected = $expect_enqueued
            ? array(array(self::HANDLE, self::SCRIPT_URL, array(), null, array('strategy' => 'async')))
            : array();

        $this->assertSame($expected, $enqueued);
    }

    public function insights_enqueue_provider(): array
    {
        return [
            'guest on checkout enqueues the library' => [true, true, false, '', true],
            'logged-in user on checkout does not enqueue' => [true, true, true, '', false],
            'guest on order-received enqueues the library' => [true, true, false, 'order-received', true],
            'logged-in user on order-received enqueues the library' => [true, true, true, 'order-received', true],
            'guest on order-pay does not enqueue' => [true, true, false, 'order-pay', false],
            'guest outside checkout does not enqueue' => [true, false, false, '', false],
            'fastlane off for guest on checkout does not enqueue' => [false, true, false, '', false],
        ];
    }

    public function tearDown(): void
    {
        unset($_GET['key']);
        unset($GLOBALS['wp']);

        parent::tearDown();
    }

    /**
     * GIVEN Fastlane is on and an order-received page for an order
     * WHEN the page enqueues its scripts with a given "key" argument
     * THEN the end-checkout tracker is enqueued only for the order key
     *
     * @dataProvider end_checkout_tracker_key_provider
     */
    public function test_end_checkout_tracker_is_enqueued_only_for_matching_order_key(
        ?string $key_in_url,
        bool $expect_enqueued
    ): void {
        $order_key = 'wc_order_abc123';

        $order = Mockery::mock(\WC_Order::class);
        $order->allows('get_order_key')->andReturn($order_key);
        $order->allows('get_total')->andReturn('10.00');
        $order->allows('get_currency')->andReturn('USD');
        $order->allows('get_payment_method')->andReturn('ppcp-axo-gateway');

        $GLOBALS['wp'] = (object) array('query_vars' => array('order-received' => '42'));
        unset($_GET['key']);
        if (null !== $key_in_url) {
            $_GET['key'] = $key_in_url;
        }

        when('absint')->alias(static fn($value) => abs((int) $value));
        when('wc_get_order')->justReturn($order);
        when('is_checkout')->justReturn(false);
        when('is_user_logged_in')->justReturn(false);
        when('is_wc_endpoint_url')->justReturn(false);
        when('wp_register_script')->justReturn(true);
        when('wp_localize_script')->justReturn(true);

        $enqueued_handles = array();
        when('wp_enqueue_script')->alias(
            static function (string $handle) use (&$enqueued_handles): void {
                $enqueued_handles[] = $handle;
            }
        );

        $asset_getter = Mockery::mock(AssetGetter::class);
        $asset_getter->allows('get_asset_url')->andReturn('https://example.test/EndCheckoutTracker.js');

        $callbacks = $this->capture_enqueue_scripts_callbacks(
            true,
            array(
                'axo.asset_getter' => $asset_getter,
                'ppcp.asset-version' => '1.0.0',
                'axo.insights' => array(),
            )
        );
        foreach ($callbacks as $callback) {
            $callback();
        }

        $expected = $expect_enqueued ? array('wc-ppcp-paypal-insights-end-checkout') : array();

        $this->assertSame($expected, $enqueued_handles);
    }

    public function end_checkout_tracker_key_provider(): array
    {
        return [
            'matching key enqueues the tracker' => ['wc_order_abc123', true],
            'wrong key does not enqueue the tracker' => ['wc_order_guess', false],
            'missing key does not enqueue the tracker' => [null, false],
        ];
    }

    private function capture_enqueue_scripts_callbacks(bool $use_fastlane, array $extra_services = array()): array
    {
        $card_configuration = Mockery::mock(CardPaymentsConfiguration::class);
        $card_configuration->allows('use_fastlane')->andReturn($use_fastlane);

        $settings_provider = Mockery::mock(SettingsProvider::class);
        $settings_provider->allows('is_method_enabled')->andReturn(true);

        $subscription_helper = Mockery::mock(SubscriptionHelper::class);
        $subscription_helper->allows('cart_contains_subscription')->andReturn(false);

        $context = Mockery::mock(Context::class);
        $context->allows('is_paypal_continuation')->andReturn(false);

        $manager = Mockery::mock(AxoManager::class);
        $manager->allows('checkout_button_renderer_hook')->andReturn('woocommerce_review_order_after_submit');

        $axo_applies = Mockery::mock(AxoApplies::class);
        $axo_applies->allows('should_render_fastlane')->andReturn(false);

        $smart_button = Mockery::mock(SmartButtonInterface::class);
        $smart_button->allows('should_load_ppcp_script')->andReturn(false);

        $services = [
            'session.handler' => null,
            'settings.settings-provider' => $settings_provider,
            'wc-subscriptions.helper' => $subscription_helper,
            'button.helper.context' => $context,
            'button.smart-button' => $smart_button,
            'axo.eligible' => true,
            'axo.manager' => $manager,
            'axo.service.axo-applies' => $axo_applies,
            'wcgateway.configuration.card-configuration' => $card_configuration,
            'axo.insights.script-url' => self::SCRIPT_URL,
        ] + $extra_services;

        $container = Mockery::mock(ContainerInterface::class);
        $container->allows('get')->andReturnUsing(static fn(string $id) => $services[$id]);
        $container->allows('has')->andReturn(false);

        $wp_loaded_callbacks = array();
        Actions\expectAdded('wp_loaded')->whenHappen(
            static function (callable $callback) use (&$wp_loaded_callbacks): void {
                $wp_loaded_callbacks[] = $callback;
            }
        );

        $enqueue_callbacks = array();
        Actions\expectAdded('wp_enqueue_scripts')->whenHappen(
            static function (callable $callback) use (&$enqueue_callbacks): void {
                $enqueue_callbacks[] = $callback;
            }
        );

        (new AxoModule())->run($container);

        $this->assertCount(1, $wp_loaded_callbacks);
        $wp_loaded_callbacks[0]();

        $this->assertNotEmpty($enqueue_callbacks);

        return $enqueue_callbacks;
    }
}
