<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Mockery;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Assets\SmartButtonInterface;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelView;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\when;

class PayPalPaymentMethodTest extends TestCase
{
    private $asset_getter;
    private $smart_button;
    private $plugin_settings;
    private $settings_status;
    private $gateway;
    private $cancellation_view;
    private $session_handler;
    private $subscription_helper;

    public function setUp(): void
    {
        parent::setUp();

        $this->asset_getter        = Mockery::mock(AssetGetter::class);
        $this->smart_button        = Mockery::mock(SmartButtonInterface::class);
        $this->plugin_settings     = Mockery::mock(SettingsProvider::class);
        $this->settings_status     = Mockery::mock(SettingsStatus::class);
        $this->gateway             = Mockery::mock(PayPalGateway::class);
        $this->cancellation_view   = Mockery::mock(CancelView::class);
        $this->session_handler     = Mockery::mock(SessionHandler::class);
        $this->subscription_helper = Mockery::mock(SubscriptionHelper::class);

        $this->gateway->id          = PayPalGateway::ID;
        $this->gateway->title       = 'PayPal';
        $this->gateway->icon        = 'https://example.test/paypal.svg';
        $this->gateway->supports    = array('products');
        $this->gateway->shouldReceive('get_description')->andReturn('Pay with PayPal');

        $this->session_handler->shouldReceive('funding_source')->andReturn('paypal');

        when('wp_create_nonce')->justReturn('nonce');

        $cart = Mockery::mock(\WC_Cart::class);
        $cart->shouldReceive('needs_shipping')->andReturn(false);
        $wc = Mockery::mock();
        $wc->cart = $cart;
        when('WC')->justReturn($wc);
    }

    /**
     * @param array<string, mixed> $script_data
     */
    private function createTestee(
        array $script_data,
        bool $add_place_order_method,
        bool $use_place_order
    ): PayPalPaymentMethod {
        $this->smart_button->shouldReceive('script_data')->andReturn($script_data);

        return new PayPalPaymentMethod(
            $this->asset_getter,
            '1.0.0',
            $this->smart_button,
            $this->plugin_settings,
            $this->settings_status,
            $this->gateway,
            false,
            $this->cancellation_view,
            $this->session_handler,
            $this->subscription_helper,
            $add_place_order_method,
            $use_place_order,
            'Place order',
            'Pay with PayPal',
            array()
        );
    }

    /**
     * GIVEN a subscription cart, a "Place order" method, and whether a vault token can be
     *      saved or manual renewals are accepted
     * WHEN get_payment_method_data() is called
     * THEN the standard "Place order" row is only enabled when a vault token can be saved
     *      or manual renewals are accepted for the subscription
     * AND under SDK v6, where the v5 smart button script data is empty, the row's smart
     *      buttons stay disabled since the v6 express button covers that surface
     *
     * @dataProvider place_order_enabled_provider
     */
    public function test_place_order_enabled(
        array $script_data,
        bool $cart_contains_subscription,
        bool $can_save_vault_token,
        bool $accept_manual_renewals,
        bool $add_place_order_method,
        bool $use_place_order,
        bool $expected,
        bool $assert_smart_buttons_disabled
    ): void {
        $this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn($cart_contains_subscription);
        $this->subscription_helper->shouldReceive('accept_manual_renewals')->andReturn($accept_manual_renewals);
        $this->plugin_settings->shouldReceive('can_save_vault_token')->andReturn($can_save_vault_token);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);

        $testee = $this->createTestee($script_data, $add_place_order_method, $use_place_order);
        $data   = $testee->get_payment_method_data();

        $this->assertSame($expected, $data['placeOrderEnabled']);

        if ($assert_smart_buttons_disabled) {
            $this->assertFalse($data['smartButtonsEnabled']);
        }
    }

    public function place_order_enabled_provider(): array
    {
        return array(
            'sdk v6, subscription cart, vaulting on, automatic renewals' => array(
                'script_data'                    => array(),
                'cart_contains_subscription'      => true,
                'can_save_vault_token'            => true,
                'accept_manual_renewals'          => false,
                'add_place_order_method'          => true,
                'use_place_order'                 => false,
                'expected'                        => true,
                'assert_smart_buttons_disabled'   => true,
            ),
            'sdk v6, subscription cart, vaulting off, automatic renewals' => array(
                'script_data'                    => array(),
                'cart_contains_subscription'      => true,
                'can_save_vault_token'            => false,
                'accept_manual_renewals'          => false,
                'add_place_order_method'          => true,
                'use_place_order'                 => false,
                'expected'                        => false,
                'assert_smart_buttons_disabled'   => true,
            ),
            'sdk v6, subscription cart, vaulting off, manual renewals accepted' => array(
                'script_data'                    => array(),
                'cart_contains_subscription'      => true,
                'can_save_vault_token'            => false,
                'accept_manual_renewals'          => true,
                'add_place_order_method'          => true,
                'use_place_order'                 => false,
                'expected'                        => true,
                'assert_smart_buttons_disabled'   => true,
            ),
            'sdk v6, no subscription in cart' => array(
                'script_data'                    => array(),
                'cart_contains_subscription'      => false,
                'can_save_vault_token'            => false,
                'accept_manual_renewals'          => false,
                'add_place_order_method'          => true,
                'use_place_order'                 => false,
                'expected'                        => true,
                'assert_smart_buttons_disabled'   => true,
            ),
            'sdk v5, subscription cart, vaulting on' => array(
                'script_data'                    => array(
                    'context'              => 'checkout-block',
                    'can_save_vault_token' => true,
                ),
                'cart_contains_subscription'      => true,
                'can_save_vault_token'            => true,
                'accept_manual_renewals'          => false,
                'add_place_order_method'          => true,
                'use_place_order'                 => false,
                'expected'                        => true,
                'assert_smart_buttons_disabled'   => false,
            ),
            'place-order method filtered off' => array(
                'script_data'                    => array(),
                'cart_contains_subscription'      => false,
                'can_save_vault_token'            => false,
                'accept_manual_renewals'          => false,
                'add_place_order_method'          => false,
                'use_place_order'                 => false,
                'expected'                        => false,
                'assert_smart_buttons_disabled'   => true,
            ),
        );
    }

    /**
     * GIVEN a gateway whose supported features vary by mode (e.g. vaulting adds
     *      "tokenization")
     * WHEN get_payment_method_data() is called
     * THEN the block declares exactly those features to WooCommerce Blocks
     */
    public function test_supported_features_come_from_the_gateway(): void
    {
        $this->gateway->supports = array('products', 'subscriptions', 'tokenization');

        $this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn(false);
        $this->subscription_helper->shouldReceive('accept_manual_renewals')->andReturn(false);
        $this->plugin_settings->shouldReceive('can_save_vault_token')->andReturn(false);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);

        $testee = $this->createTestee(array(), true, false);
        $data   = $testee->get_payment_method_data();

        $this->assertSame(array('products', 'subscriptions', 'tokenization'), $data['supportedFeatures']);
    }
}
