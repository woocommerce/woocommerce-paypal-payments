<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Assets;

use Mockery;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ButtonStyleMapper;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelView;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Functions\when;

class SdkV6ManagerTest extends TestCase
{
    private $asset_getter;
    private $environment;
    private $style_mapper;
    private $settings_status;
    private $context;
    private $session_handler;
    private $cancel_view;
    private $card_payments_configuration;
	private $card_vaulting_enabled;
    private $subscription_helper;
	private $credit_card_icons;

    public function setUp(): void
    {
        parent::setUp();

        $this->asset_getter = Mockery::mock(AssetGetter::class);
        $this->environment = Mockery::mock(Environment::class);
        $this->style_mapper = Mockery::mock(ButtonStyleMapper::class);
        $this->settings_status = Mockery::mock(SettingsStatus::class);
        $this->context = Mockery::mock(Context::class);
        $this->session_handler = Mockery::mock(SessionHandler::class);
        $this->cancel_view = Mockery::mock(CancelView::class);
        $this->card_payments_configuration = Mockery::mock(CardPaymentsConfiguration::class);
        $this->card_vaulting_enabled = true;
		$this->subscription_helper = Mockery::mock(SubscriptionHelper::class);
        $this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn(false)->byDefault();
		$this->credit_card_icons = [];
    }

    private function createTestee(bool $should_handle_shipping = false, array $credit_card_icons = []): SdkV6Manager
    {
        return new SdkV6Manager(
            $this->asset_getter,
            '1.0.0',
            $this->environment,
            $this->style_mapper,
            $should_handle_shipping,
            $this->settings_status,
            $this->context,
            $this->session_handler,
            $this->cancel_view,
            false,
            false,
            $this->card_payments_configuration,
	        $this->card_vaulting_enabled,
	        $this->subscription_helper,
	        $this->credit_card_icons
        );
    }

    public function tearDown(): void
    {
        global $wp;
        $wp = null;
        unset($_GET['key']);

        parent::tearDown();
    }

    /**
     * GIVEN the buyer is on a page context where card fields could render
     * WHEN checking whether the v6 Advanced Card Fields should render
     * THEN the result depends on both the page context and the ACDC configuration
     *
     * @dataProvider card_fields_enablement_provider
     */
    public function testIsCardFieldsEnabled(string $page_context, bool $acdc_enabled, bool $expected): void
    {
        $this->context->shouldReceive('context')->andReturn($page_context);
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn($acdc_enabled);

        $testee = $this->createTestee();

        $this->assertSame($expected, $testee->is_card_fields_enabled());
    }

    public function card_fields_enablement_provider(): array
    {
        return [
            'checkout-block with ACDC enabled renders card fields' => ['checkout-block', true, true],
            'classic checkout with ACDC enabled renders card fields' => ['checkout', true, true],
            'checkout-block with ACDC disabled does not render card fields' => ['checkout-block', false, false],
            'classic checkout with ACDC disabled does not render card fields' => ['checkout', false, false],
            'cart-block is never eligible for card fields' => ['cart-block', true, false],
            'product page is never eligible for card fields' => ['product', true, false],
            'pay-now with ACDC enabled renders card fields' => ['pay-now', true, true],
            'pay-now with ACDC disabled does not render card fields' => ['pay-now', false, false],
        ];
    }

    /**
     * GIVEN a merchant with smart buttons enabled for every location
     * WHEN determining which locations should render on the current page
     * THEN the cart, checkout and mini-cart wallet buttons are suppressed whenever the cart
     *      does not need payment (e.g. a $0 order from a full-value coupon)
     * AND the product and pay-now locations render regardless of the cart's payment status,
     *     since pay-now is driven by an existing WC order rather than the cart
     *
     * @dataProvider render_places_needs_payment_provider
     */
    public function testDetermineRenderPlacesGatedByCartNeedsPayment(?bool $cart_needs_payment, array $expected): void
    {
        $this->context->shouldReceive('init_context')->once();
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(true);

        if (null === $cart_needs_payment) {
            when('WC')->justReturn((object) ['cart' => null]);
        } else {
            $cart = Mockery::mock();
            $cart->shouldReceive('needs_payment')->andReturn($cart_needs_payment);
            when('WC')->justReturn((object) ['cart' => $cart]);
        }

        $testee = $this->createTestee();

        $this->assertSame($expected, $testee->determine_render_places());
    }

    public function render_places_needs_payment_provider(): array
    {
        return [
            'cart needing payment enables cart, checkout and mini-cart' => [
                true,
                ['product' => true, 'cart' => true, 'checkout' => true, 'pay-now' => true, 'mini-cart' => true],
            ],
            'zero-total cart not needing payment suppresses cart, checkout and mini-cart' => [
                false,
                ['product' => true, 'cart' => false, 'checkout' => false, 'pay-now' => true, 'mini-cart' => false],
            ],
            'no cart present is treated as needing payment' => [
                null,
                ['product' => true, 'cart' => true, 'checkout' => true, 'pay-now' => true, 'mini-cart' => true],
            ],
        ];
    }

    /**
     * GIVEN the mini-cart smart button location is enabled sitewide
     * WHEN checking whether the v6 SDK should load on a page with no matching page context
     *      (e.g. the shop or home page, where a block Mini-Cart may still appear)
     * THEN the SDK loads sitewide purely because the mini-cart location is enabled,
     *      without requiring the classic mini-cart widget to be active
     */
    public function testShouldLoadSitewideWhenMiniCartEnabledRegardlessOfWidget(): void
    {
        $this->context->shouldReceive('context')->andReturn('');
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('mini-cart')
            ->andReturn(true);

        $testee = $this->createTestee();

        $this->assertTrue($testee->should_load_on_current_page());
    }

    /**
     * GIVEN the mini-cart smart button location is disabled and the page has no matching context
     * WHEN checking whether the v6 SDK should load on the current page
     * THEN the SDK does not load
     */
    public function testShouldNotLoadWhenMiniCartDisabledAndNoMatchingPageContext(): void
    {
        $this->context->shouldReceive('context')->andReturn('');
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('mini-cart')
            ->andReturn(false);

        $testee = $this->createTestee();

        $this->assertFalse($testee->should_load_on_current_page());
    }

    /**
     * GIVEN a buyer on the pay-for-order page for an existing WC order
     * WHEN the SDK bootstrap data is generated
     * THEN the pay_now identifiers (order id and order key) are forwarded so the front end
     *      can create the PayPal order from the existing WC order
     * AND shipping is not handled in PayPal for the pay-now page, matching checkout
     */
    public function testScriptDataIncludesPayNowIdentifiers(): void
    {
        global $wp;
        $wp = (object) ['query_vars' => ['order-pay' => 123]];
        $_GET['key'] = 'wc_order_abc123';

        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_order_key')->andReturn('wc_order_abc123');
        $order->shouldReceive('get_total')->andReturn('49.99');

        $this->context->shouldReceive('context')->andReturn('pay-now');
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->card_payments_configuration->shouldReceive('gateway_title')->andReturn('Credit Card');
        $this->card_payments_configuration->shouldReceive('show_name_on_card')->andReturn('no');

        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);
        $this->session_handler->shouldReceive('order')->andReturn(null);
        $this->context->shouldReceive('is_paypal_continuation')->andReturn(false);
        $this->environment->shouldReceive('is_sandbox')->andReturn(false);
        $this->style_mapper->shouldReceive('styles_for_context')->andReturn([]);

        when('WC')->justReturn((object) [
            'customer' => null,
            'cart'     => null,
        ]);
        when('wc_get_order')->justReturn($order);
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('is_product')->justReturn(false);
        when('rest_url')->justReturn('https://example.com/wp-json/wc/store/v1/cart');
        when('wp_create_nonce')->justReturn('nonce');
        when('wc_get_checkout_url')->justReturn('https://example.com/checkout');

        $testee = $this->createTestee(true);
        $data   = $testee->script_data();

        $this->assertSame(
            ['order_id' => 123, 'order_key' => 'wc_order_abc123'],
            $data['pay_now']
        );
        $this->assertSame('49.99', $data['amount']);
        $this->assertFalse($data['shipping']['handle_in_paypal']);
    }

    /**
     * GIVEN a checkout block page with Advanced Card Fields enabled for the merchant
     * WHEN the SDK bootstrap data is generated
     * THEN card_fields.enabled is true
     * AND the gateway title and name-field flag are carried into the payload
     * AND is_vaulting_enabled reflects the card vaulting setting
     * AND has_subscriptions reflects whether the cart contains a subscription
     *
     * @dataProvider script_data_card_fields_provider
     */
    public function testScriptDataCardFields(
        string $page_context,
        bool $acdc_enabled,
        string $gateway_title,
        string $show_name_on_card,
        bool $card_vaulting_enabled,
        bool $cart_contains_subscription,
        bool $expected_enabled,
        bool $expected_name_field
    ): void {
        $this->context->shouldReceive('context')->andReturn($page_context);
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn($acdc_enabled);
        $this->card_payments_configuration->shouldReceive('gateway_title')->andReturn($gateway_title);
        $this->card_payments_configuration->shouldReceive('show_name_on_card')->andReturn($show_name_on_card);
        $this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn($cart_contains_subscription);

        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);
        $this->session_handler->shouldReceive('order')->andReturn(null);
        $this->context->shouldReceive('is_paypal_continuation')->andReturn(false);
        $this->environment->shouldReceive('is_sandbox')->andReturn(false);
        $this->style_mapper->shouldReceive('styles_for_context')->andReturn([
            'colorClass'   => 'paypal-gold',
            'borderRadius' => '24px',
        ]);

        when('WC')->justReturn((object) [
            'customer' => null,
            'cart'     => null,
        ]);
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('is_product')->justReturn(false);
        when('rest_url')->justReturn('https://example.com/wp-json/wc/store/v1/cart');
        when('wp_create_nonce')->justReturn('nonce');
        when('wc_get_checkout_url')->justReturn('https://example.com/checkout');

        $testee = $this->createTestee($card_vaulting_enabled);
        $data   = $testee->script_data();

        $this->assertSame($expected_enabled, $data['card_fields']['enabled']);
        $this->assertSame($gateway_title, $data['card_fields']['title']);
        $this->assertSame($expected_name_field, $data['card_fields']['name_field']);
        $this->assertSame(CreditCardGateway::ID, $data['card_fields']['payment_method']);
        $this->assertSame($card_vaulting_enabled, $data['card_fields']['is_vaulting_enabled']);
        $this->assertSame($cart_contains_subscription, $data['card_fields']['has_subscriptions']);
    }

    public function script_data_card_fields_provider(): array
    {
        return [
            'checkout-block with ACDC enabled and name field shown' => [
                'checkout-block', true, 'Credit Card', 'yes', false, false, true, true,
            ],
            'checkout-block with ACDC enabled and name field hidden' => [
                'checkout-block', true, 'Credit Card', 'no', false, false, true, false,
            ],
            'checkout-block with ACDC disabled' => [
                'checkout-block', false, 'Credit Card', 'yes', false, false, false, true,
            ],
            'classic checkout with ACDC enabled' => [
                'checkout', true, 'Credit Card', 'yes', false, false, true, true,
            ],
            'checkout-block with card vaulting enabled' => [
                'checkout-block', true, 'Credit Card', 'yes', true, false, true, true,
            ],
            'checkout-block with a subscription in the cart' => [
                'checkout-block', true, 'Credit Card', 'yes', false, true, true, true,
            ],
        ];
    }

    /**
     * GIVEN the merchant has configured a set of credit-card brand icons to display
     * WHEN the SDK bootstrap data is generated
     * THEN each icon is mapped into the card_fields.card_icons payload with its id, alt text and src URL
     * AND an empty icon configuration produces an empty card_icons list
     *
     * @dataProvider credit_card_icons_provider
     */
    public function testScriptDataCardFieldsIncludesCreditCardIcons(array $credit_card_icons, array $expected_card_icons): void
    {
        $this->context->shouldReceive('context')->andReturn('checkout-block');
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(true);
        $this->card_payments_configuration->shouldReceive('gateway_title')->andReturn('Credit Card');
        $this->card_payments_configuration->shouldReceive('show_name_on_card')->andReturn('yes');

        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);
        $this->session_handler->shouldReceive('order')->andReturn(null);
        $this->context->shouldReceive('is_paypal_continuation')->andReturn(false);
        $this->environment->shouldReceive('is_sandbox')->andReturn(false);
        $this->style_mapper->shouldReceive('styles_for_context')->andReturn([]);

        when('WC')->justReturn((object) [
            'customer' => null,
            'cart'     => null,
        ]);
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('is_product')->justReturn(false);
        when('rest_url')->justReturn('https://example.com/wp-json/wc/store/v1/cart');
        when('wp_create_nonce')->justReturn('nonce');
        when('wc_get_checkout_url')->justReturn('https://example.com/checkout');

        $testee = $this->createTestee(false, $credit_card_icons);
        $data   = $testee->script_data();

        $this->assertSame($expected_card_icons, $data['card_fields']['card_icons']);
    }

    public function credit_card_icons_provider(): array
    {
        return [
            'configured icons are mapped to id, alt and src' => [
                [['type' => 'visa', 'title' => 'Visa', 'url' => 'https://x/visa.svg']],
                [['id' => 'visa', 'alt' => 'Visa', 'src' => 'https://x/visa.svg']],
            ],
            'no configured icons produces an empty list' => [
                [],
                [],
            ],
        ];
    }
}
