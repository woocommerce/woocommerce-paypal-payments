<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Assets;

use Mockery;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ApplePayConfig;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ButtonStyleMapper;
use WooCommerce\PayPalCommerce\SdkV6\Helper\FastlaneConfig;
use WooCommerce\PayPalCommerce\SdkV6\Helper\GooglePayConfig;
use WooCommerce\PayPalCommerce\SdkV6\Helper\MessagesEligibility;
use WooCommerce\PayPalCommerce\SdkV6\Helper\MessageStyleMapper;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelView;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use function Brain\Monkey\Actions\expectDone;
use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Functions\expect;
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
    private $subscription_helper;
	private $credit_card_icons;
	private $message_style_mapper;
	private $messages_eligibility;
	private $google_pay_config;
	private $apple_pay_config;
	private $fastlane_config;

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
		$this->subscription_helper = Mockery::mock(SubscriptionHelper::class);
        $this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn(false)->byDefault();
		$this->credit_card_icons = [];

		$this->message_style_mapper = Mockery::mock(MessageStyleMapper::class);
		$this->message_style_mapper->shouldReceive('styles_for_location')->andReturn([
			'logoType'     => 'WORDMARK',
			'logoPosition' => 'LEFT',
			'textColor'    => 'BLACK',
			'fontSize'     => '',
		])->byDefault();

		$this->messages_eligibility = Mockery::mock(MessagesEligibility::class);
		$this->messages_eligibility->shouldReceive('is_enabled_for_location')->andReturn(false)->byDefault();
		$this->messages_eligibility->shouldReceive('is_hidden')->andReturn(false)->byDefault();

		$this->context->shouldReceive('location')->andReturn('')->byDefault();

		when('is_admin')->justReturn(false);

		$this->google_pay_config = Mockery::mock(GooglePayConfig::class);
		$this->google_pay_config->shouldReceive('should_render')->andReturn(false)->byDefault();

		$this->apple_pay_config = Mockery::mock(ApplePayConfig::class);
		$this->apple_pay_config->shouldReceive('should_render')->andReturn(false)->byDefault();
		$this->apple_pay_config->shouldReceive('display_name')->andReturn('Test Store')->byDefault();

		$this->fastlane_config = Mockery::mock(FastlaneConfig::class);
		$this->fastlane_config->shouldReceive('should_render')->andReturn(false)->byDefault();

		// Reached unconditionally by script_data()'s Apple Pay validation block.
		when('admin_url')->justReturn('https://example.com/wp-admin/admin-ajax.php');
		when('wp_create_nonce')->justReturn('nonce');
    }

    /**
     * A WC() stub carrying an empty cart/customer and a payment-gateways
     * registry with no gateways available, matching the wallet defaults'
     * "does not render" state used throughout this test.
     */
    private function create_wc_stub(): object
    {
        $payment_gateways = Mockery::mock();
        $payment_gateways->shouldReceive('get_available_payment_gateways')->andReturn([])->byDefault();

        $wc = Mockery::mock();
        $wc->customer = null;
        $wc->cart = null;
        $wc->shouldReceive('payment_gateways')->andReturn($payment_gateways)->byDefault();

        return $wc;
    }

    private function createTestee(bool $should_handle_shipping = false, array $credit_card_icons = [], bool $card_vaulting_enabled = true, string $merchant_country = 'US'): SdkV6Manager
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
	        $card_vaulting_enabled,
	        $this->subscription_helper,
	        $credit_card_icons,
	        $this->message_style_mapper,
	        $this->messages_eligibility,
	        $merchant_country,
	        $this->google_pay_config,
	        $this->apple_pay_config,
	        $this->fastlane_config
        );
    }

    private function stubScriptDataBaseline(string $page_context, string $location): void
    {
        $this->context->shouldReceive('context')->andReturn($page_context);
        $this->context->shouldReceive('location')->andReturn($location);
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->card_payments_configuration->shouldReceive('gateway_title')->andReturn('Credit Card');
        $this->card_payments_configuration->shouldReceive('show_name_on_card')->andReturn('no');
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);
        $this->session_handler->shouldReceive('order')->andReturn(null);
        $this->context->shouldReceive('is_paypal_continuation')->andReturn(false);
        $this->environment->shouldReceive('is_sandbox')->andReturn(false);
        $this->style_mapper->shouldReceive('styles_for_context')->andReturn([]);

        when('WC')->justReturn($this->create_wc_stub());
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('is_product')->justReturn(false);
        when('rest_url')->justReturn('https://example.com/wp-json/wc/store/v1/cart');
        when('wp_create_nonce')->justReturn('nonce');
        when('wc_get_checkout_url')->justReturn('https://example.com/checkout');
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
     * AND determine_render_places() does not call Context::init_context() itself — that
     *     initialization now happens once in SdkV6Module's 'wp' callback, before both the
     *     button and message hook registrars run, so neither registrar depends on the other
     *     running first to trip it as a side effect. Reintroducing the call here would restore
     *     that ordering coupling.
     *
     * @dataProvider render_places_needs_payment_provider
     */
    public function testDetermineRenderPlacesGatedByCartNeedsPayment(?bool $cart_needs_payment, array $expected): void
    {
        $this->context->shouldReceive('init_context')->never();
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
     * GIVEN the mini-cart smart button location is disabled, the page has no matching
     *       context, and Pay Later messaging is explicitly ineligible for the resolved
     *       (empty) location
     * WHEN checking whether the v6 SDK should load on the current page
     * THEN the SDK does not load
     */
    public function testShouldNotLoadWhenMiniCartDisabledAndNoMatchingPageContext(): void
    {
        $this->context->shouldReceive('context')->andReturn('');
        $this->context->shouldReceive('location')->andReturn('');
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('mini-cart')
            ->andReturn(false);
        $this->messages_eligibility->shouldReceive('is_enabled_for_location')->with('')->andReturn(false);

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

        when('WC')->justReturn($this->create_wc_stub());
        when('wc_get_order')->justReturn($order);
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('is_product')->justReturn(false);
        when('rest_url')->justReturn('https://example.com/wp-json/wc/store/v1/cart');
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
     * GIVEN a merchant whose PayPal processing country differs from the buyer's
     *       billing country
     * WHEN the SDK bootstrap data is generated
     * THEN merchant_country carries the merchant's own country, not the buyer's,
     *      since a wallet sheet states where the payment is processed
     */
    public function testScriptDataIncludesMerchantCountryIndependentOfBuyerCountry(): void
    {
        $this->context->shouldReceive('context')->andReturn('checkout');
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->card_payments_configuration->shouldReceive('gateway_title')->andReturn('Credit Card');
        $this->card_payments_configuration->shouldReceive('show_name_on_card')->andReturn('no');

        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);
        $this->session_handler->shouldReceive('order')->andReturn(null);
        $this->context->shouldReceive('is_paypal_continuation')->andReturn(false);
        $this->environment->shouldReceive('is_sandbox')->andReturn(false);
        $this->style_mapper->shouldReceive('styles_for_context')->andReturn([]);

        $wc = $this->create_wc_stub();
        $wc->customer = Mockery::mock();
        $wc->customer->shouldReceive('get_billing_country')->andReturn('DE');

        when('WC')->justReturn($wc);
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('is_product')->justReturn(false);
        when('rest_url')->justReturn('https://example.com/wp-json/wc/store/v1/cart');
        when('wc_get_checkout_url')->justReturn('https://example.com/checkout');

        $testee = $this->createTestee(false, [], true, 'FR');
        $data   = $testee->script_data();

        $this->assertSame('FR', $data['merchant_country']);
        $this->assertSame('DE', $data['buyer_country']);
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

        when('WC')->justReturn($this->create_wc_stub());
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('is_product')->justReturn(false);
        when('rest_url')->justReturn('https://example.com/wp-json/wc/store/v1/cart');
        when('wc_get_checkout_url')->justReturn('https://example.com/checkout');

        $testee = $this->createTestee(false, [], $card_vaulting_enabled);
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

        when('WC')->justReturn($this->create_wc_stub());
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('is_product')->justReturn(false);
        when('rest_url')->justReturn('https://example.com/wp-json/wc/store/v1/cart');
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

    // -------------------------------------------------------------------------
    // should_load_on_current_page(): button, ACDC and messaging conditions
    // -------------------------------------------------------------------------

    /**
     * GIVEN a WooCommerce Blocks cart or checkout page, where should_load_on_current_page()
     *       is only allowed to answer for the payment-method surface (button locations and
     *       ACDC), never for Pay Later messaging alone
     * WHEN checking whether the v6 SDK should load a payment-method-owning surface,
     *      once with Pay Later messaging eligible for the page and once with it ineligible
     * THEN should_load_on_current_page() returns the same result either way
     *
     * Two callers narrow this predicate further with is_block_context():
     * V6PaymentMethod::is_active() and the v5 block-method unregistration in SdkV6Module
     * (`&& is_block_context()`). Both rely on seeing only the payment-method answer in
     * block contexts. If this invariant ever breaks, v6 express buttons get registered on
     * block pages where the merchant disabled them, and the v5 Google Pay, Apple Pay and
     * Fastlane block methods get unregistered with no v6 replacement behind them.
     *
     * Context::location() is stubbed here (not just context()) so the messaging chain is
     * genuinely exercised down to MessagesEligibility::is_enabled_for_location(), called
     * with the normalized location — otherwise this reduces to false === false for a
     * reason unrelated to block contexts.
     *
     * Block contexts are excluded from messaging by two independent mechanisms in
     * production: should_load_messages()'s own is_block_context() early-out, and
     * messages_render_hook() returning null for block locations (its switch has no block
     * cases). This test only fails when BOTH are undone at once; removing either alone
     * still leaves the other vetoing messaging, so it cannot pin one mechanism in
     * isolation. That redundancy is deliberate today, but it is exactly what the future
     * "make block messaging claim a page on its own" change would need to undo, and
     * that change must revisit both should_load_messages() and messages_render_hook()
     * together. testMessagesRenderHookReturnsNullForBlockLocations() pins the second
     * mechanism (messages_render_hook()) on its own; the first (is_block_context()) has
     * no standalone test — see that test's docblock for why.
     *
     * @dataProvider blockContextProvider
     */
    public function testShouldLoadOnCurrentPageInBlockContextsIsUnaffectedByMessagingEligibility(
        string $page_context,
        string $normalized_location
    ): void {
        $this->context->shouldReceive('context')->andReturn($page_context);
        $this->context->shouldReceive('location')->andReturn($page_context);
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);

        $this->messages_eligibility->shouldReceive('is_enabled_for_location')
            ->with($normalized_location)
            ->andReturn(true);
        $withMessagingEnabled = $this->createTestee()->should_load_on_current_page();

        $this->messages_eligibility->shouldReceive('is_enabled_for_location')
            ->with($normalized_location)
            ->andReturn(false);
        $withMessagingDisabled = $this->createTestee()->should_load_on_current_page();

        $this->assertSame($withMessagingEnabled, $withMessagingDisabled);
    }

    public function blockContextProvider(): array
    {
        return [
            'cart block'     => ['cart-block', 'cart'],
            'checkout block' => ['checkout-block', 'checkout'],
        ];
    }

    /**
     * GIVEN a page context on which Fastlane's own config would allow it to render
     * WHEN checking whether Fastlane is enabled for the current page
     * THEN the answer follows FastlaneConfig::should_render() for that context
     *
     * @dataProvider fastlane_enablement_provider
     */
    public function testIsFastlaneEnabledDelegatesToFastlaneConfig(bool $should_render, bool $expected): void
    {
        $this->context->shouldReceive('context')->andReturn('checkout');
        $this->fastlane_config->shouldReceive('should_render')->with('checkout')->andReturn($should_render);

        $testee = $this->createTestee();

        $this->assertSame($expected, $testee->is_fastlane_enabled());
    }

    public function fastlane_enablement_provider(): array
    {
        return [
            'FastlaneConfig allowing render enables Fastlane' => [true, true],
            'FastlaneConfig refusing render disables Fastlane' => [false, false],
        ];
    }

    /**
     * GIVEN a page whose location has smart buttons enabled
     * WHEN checking whether the v6 SDK should load on the current page, for any reason
     * THEN it loads
     */
    public function testShouldLoadOnCurrentPageTrueWhenButtonLocationEnabled(): void
    {
        $this->context->shouldReceive('context')->andReturn('checkout');
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->with('checkout')->andReturn(true);

        $testee = $this->createTestee();

        $this->assertTrue($testee->should_load_on_current_page());
    }

    /**
     * GIVEN a classic checkout page with no smart button enabled anywhere, but Pay Later
     *       messaging eligible on that page
     * WHEN checking whether the v6 SDK should load on the current page
     * THEN it still loads, purely to render the message
     */
    public function testShouldLoadOnCurrentPageTrueWhenOnlyMessagingIsEnabledOnAClassicPage(): void
    {
        $this->context->shouldReceive('context')->andReturn('checkout');
        $this->context->shouldReceive('location')->andReturn('checkout');
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);
        $this->messages_eligibility->shouldReceive('is_enabled_for_location')->with('checkout')->andReturn(true);

        $testee = $this->createTestee();

        $this->assertTrue($testee->should_load_on_current_page());
    }

    /**
     * GIVEN a WooCommerce Blocks cart or checkout page, with Pay Later messaging eligible
     * WHEN checking whether the v6 SDK should load on the current page
     * THEN it does not load through the messaging path — block pages get the SDK through
     *      the block payment method instead, gated on the button/ACDC conditions alone
     *
     * Context::location() is stubbed to the block location (not just context()) and
     * MessagesEligibility::is_enabled_for_location() is stubbed to true for the
     * normalized location, so the false result genuinely comes from the block-context
     * exclusion rather than from an unstubbed location() defaulting to null/empty.
     *
     * This is a concrete instance of the invariant pinned by
     * testShouldLoadOnCurrentPageInBlockContextsIsUnaffectedByMessagingEligibility()
     * (messaging eligibility never changes the block-context answer); kept alongside it
     * because it also documents the specific value (false) that answer takes, which the
     * invariant test deliberately leaves unspecified. Like that test, it is only
     * mutation-sensitive to should_load_messages()'s and messages_render_hook()'s block
     * exclusions being removed together, not to either alone — see the redundancy note
     * there.
     *
     * @dataProvider blockContextProvider
     */
    public function testShouldLoadOnCurrentPageFalseInBlockContextsEvenWhenMessagingEnabled(
        string $page_context,
        string $normalized_location
    ): void {
        $this->context->shouldReceive('context')->andReturn($page_context);
        $this->context->shouldReceive('location')->andReturn($page_context);
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);
        $this->messages_eligibility->shouldReceive('is_enabled_for_location')
            ->with($normalized_location)
            ->andReturn(true);

        $testee = $this->createTestee();

        $this->assertFalse($testee->should_load_on_current_page());
    }

    /**
     * GIVEN a page whose location resolves to shop, home or no location at all
     * WHEN checking whether the v6 SDK should load on the current page
     * THEN it does not load — this module never places a message on these pages, so
     *      messages_render_hook() returning null must veto the messaging-only claim
     *
     * @dataProvider unsupportedMessageLocationProvider
     */
    public function testShouldLoadOnCurrentPageFalseWhenMessagesRenderHookIsNull(string $location): void
    {
        $this->context->shouldReceive('context')->andReturn('');
        $this->context->shouldReceive('location')->andReturn($location);
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);
        $this->messages_eligibility->shouldReceive('is_enabled_for_location')->andReturn(true);

        $testee = $this->createTestee();

        $this->assertFalse($testee->should_load_on_current_page());
    }

    public function unsupportedMessageLocationProvider(): array
    {
        return [
            'shop page'    => ['shop'],
            'home page'    => ['home'],
            'no location'  => [''],
        ];
    }

    /**
     * GIVEN a classic page in wp-admin, one where Pay Later messaging would otherwise be
     *       eligible
     * WHEN checking whether the v6 SDK should load on the current page
     * THEN it does not load, even though nothing else would have vetoed messaging
     *
     * Messaging is stubbed eligible for the checkout location so the false result is not
     * an accident of an unstubbed location() silently keeping messaging off.
     *
     * is_admin() is checked twice in production — once in should_load_messages() and
     * again, independently, inside messages_enabled() — so this test is only
     * mutation-sensitive to both checks being removed together, not to either alone.
     * That duplication is real redundancy in the production code, not a test defect.
     */
    public function testShouldLoadOnCurrentPageFalseUnderIsAdmin(): void
    {
        when('is_admin')->justReturn(true);
        $this->context->shouldReceive('context')->andReturn('checkout');
        $this->context->shouldReceive('location')->andReturn('checkout');
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);
        $this->messages_eligibility->shouldReceive('is_enabled_for_location')->with('checkout')->andReturn(true);

        $testee = $this->createTestee();

        $this->assertFalse($testee->should_load_on_current_page());
    }

    // -------------------------------------------------------------------------
    // Block location normalization for the messaging eligibility lookup
    // -------------------------------------------------------------------------

    /**
     * GIVEN the current page resolves to a block cart/checkout location, or to pay-now
     * WHEN checking whether Pay Later messaging is enabled
     * THEN MessagesEligibility::is_enabled_for_location() is called with the normalized
     *      messaging-settings location, not the raw block/page location
     *
     * SettingsStatus::normalize_location() would turn 'checkout-block' into
     * 'checkout-block-express', a location the messaging settings never contain, so
     * skipping this normalization would silently disable messaging on block checkout.
     *
     * @dataProvider blockLocationNormalizationProvider
     */
    public function testMessagesEnabledNormalizesBlockLocationsForEligibilityLookup(string $raw_location, string $expected_location): void
    {
        $this->context->shouldReceive('location')->andReturn($raw_location);
        $this->messages_eligibility
            ->shouldReceive('is_enabled_for_location')
            ->once()
            ->with($expected_location)
            ->andReturn(true);

        $testee = $this->createTestee();

        $this->assertTrue($testee->messages_enabled());
    }

    public function blockLocationNormalizationProvider(): array
    {
        return [
            'checkout-block normalizes to checkout, not checkout-block-express' => ['checkout-block', 'checkout'],
            'cart-block normalizes to cart'                                      => ['cart-block', 'cart'],
            'pay-now normalizes to checkout'                                     => ['pay-now', 'checkout'],
        ];
    }

    // -------------------------------------------------------------------------
    // script_data()['messages']
    // -------------------------------------------------------------------------

    /**
     * GIVEN Pay Later messaging is not enabled for the current page
     * WHEN the SDK bootstrap data is generated
     * THEN the messages payload still carries all six documented keys, with enabled: false
     *      rather than the key being omitted — so the bootstrap can branch on it directly
     */
    public function testScriptDataMessagesShapeIncludesAllKeysEvenWhenDisabled(): void
    {
        $this->stubScriptDataBaseline('checkout', 'checkout');
        $this->messages_eligibility->shouldReceive('is_enabled_for_location')->andReturn(false);
        $this->messages_eligibility->shouldReceive('is_hidden')->with('checkout')->andReturn(false);
        $this->message_style_mapper->shouldReceive('styles_for_location')->with('checkout')->andReturn([
            'logoType' => 'WORDMARK', 'logoPosition' => 'LEFT', 'textColor' => 'BLACK', 'fontSize' => '',
        ]);

        $testee = $this->createTestee();
        $data   = $testee->script_data();

        $this->assertSame(
            ['enabled', 'wrapper', 'is_hidden', 'amount', 'page_type', 'style'],
            array_keys($data['messages'])
        );
        $this->assertFalse($data['messages']['enabled']);
    }

    // -------------------------------------------------------------------------
    // messages_amount() — product-first, unlike transaction_amount()
    // -------------------------------------------------------------------------

    /**
     * GIVEN a product page while the cart already holds other, different-priced items
     * WHEN the SDK bootstrap data is generated
     * THEN the Pay Later message prices the product being viewed, not the cart total
     * AND the button-eligibility amount (transaction_amount) remains cart-first,
     *     the opposite behaviour
     */
    public function testMessagesAmountIsProductFirstOnProductPageEvenWithNonEmptyCart(): void
    {
        $this->stubScriptDataBaseline('product', 'product');

        $product = Mockery::mock(\WC_Product::class);
        when('wc_get_product')->justReturn($product);
        when('wc_get_price_including_tax')->justReturn(29.99);

        $cart = Mockery::mock();
        $cart->shouldReceive('is_empty')->andReturn(false);
        $cart->shouldReceive('get_total')->with('edit')->andReturn('99.99');
        $cart->shouldReceive('needs_shipping')->andReturn(false);
        when('WC')->justReturn((object) ['customer' => null, 'cart' => $cart]);

        $this->messages_eligibility->shouldReceive('is_enabled_for_location')->andReturn(false);
        $this->messages_eligibility->shouldReceive('is_hidden')->andReturn(false);
        $this->message_style_mapper->shouldReceive('styles_for_location')->andReturn([]);

        $testee = $this->createTestee();
        $data   = $testee->script_data();

        $this->assertSame('29.99', $data['messages']['amount']);
        $this->assertSame('99.99', $data['amount']);
    }

    /**
     * GIVEN a buyer on the pay-for-order page for an existing WC order
     * WHEN the SDK bootstrap data is generated
     * THEN the Pay Later message prices the validated order total
     */
    public function testMessagesAmountUsesValidatedOrderTotalOnPayNowPage(): void
    {
        global $wp;
        $wp = (object) ['query_vars' => ['order-pay' => 42]];
        $_GET['key'] = 'order-key-abc';

        $order = Mockery::mock(\WC_Order::class);
        $order->shouldReceive('get_order_key')->andReturn('order-key-abc');
        $order->shouldReceive('get_total')->andReturn('150.00');

        $this->stubScriptDataBaseline('pay-now', 'pay-now');
        when('wc_get_order')->justReturn($order);

        $this->messages_eligibility->shouldReceive('is_enabled_for_location')->andReturn(false);
        $this->messages_eligibility->shouldReceive('is_hidden')->andReturn(false);
        $this->message_style_mapper->shouldReceive('styles_for_location')->andReturn([]);

        $testee = $this->createTestee();
        $data   = $testee->script_data();

        $this->assertSame('150.00', $data['messages']['amount']);
    }

    /**
     * GIVEN neither a product, a cart, nor a pay-for-order order is available
     * WHEN the SDK bootstrap data is generated
     * THEN the Pay Later message amount falls back to an empty string
     */
    public function testMessagesAmountFallsBackToEmptyStringWhenNothingIsAvailable(): void
    {
        $this->stubScriptDataBaseline('checkout', 'checkout');
        when('wc_get_product')->justReturn(null);

        $this->messages_eligibility->shouldReceive('is_enabled_for_location')->andReturn(false);
        $this->messages_eligibility->shouldReceive('is_hidden')->andReturn(false);
        $this->message_style_mapper->shouldReceive('styles_for_location')->andReturn([]);

        $testee = $this->createTestee();
        $data   = $testee->script_data();

        $this->assertSame('', $data['messages']['amount']);
    }

    // -------------------------------------------------------------------------
    // messages_render_hook()
    // -------------------------------------------------------------------------

    /**
     * GIVEN a page location this module places a message on
     * WHEN resolving the message render hook, with no merchant filter overriding it
     * THEN the documented default hook name and priority are returned
     *
     * @dataProvider defaultMessagesRenderHookProvider
     */
    public function testMessagesRenderHookReturnsDocumentedDefaults(string $location, string $expected_name, int $expected_priority): void
    {
        $this->context->shouldReceive('location')->andReturn($location);

        $testee = $this->createTestee();
        $hook   = $testee->messages_render_hook();

        $this->assertSame(['name' => $expected_name, 'priority' => $expected_priority], $hook);
    }

    public function defaultMessagesRenderHookProvider(): array
    {
        return [
            'checkout' => ['checkout', 'woocommerce_review_order_before_payment', 10],
            'cart'     => ['cart', 'woocommerce_proceed_to_checkout', 19],
            'product'  => ['product', 'woocommerce_single_product_summary', 30],
            'pay-now'  => ['pay-now', 'woocommerce_pay_order_before_submit', 10],
        ];
    }

    /**
     * GIVEN a location this module does not place a message on (shop, home, or none)
     * WHEN resolving the message render hook
     * THEN null is returned, so this module stays out of the way and leaves the page to v5
     *
     * @dataProvider unsupportedRenderHookLocationProvider
     */
    public function testMessagesRenderHookReturnsNullForPagesThisModuleDoesNotServe(string $location): void
    {
        $this->context->shouldReceive('location')->andReturn($location);

        $testee = $this->createTestee();

        $this->assertNull($testee->messages_render_hook());
    }

    public function unsupportedRenderHookLocationProvider(): array
    {
        return [
            'shop'  => ['shop'],
            'home'  => ['home'],
            'empty' => [''],
        ];
    }

    /**
     * GIVEN the current page resolves to a block cart or checkout location
     * WHEN resolving the message render hook
     * THEN null is returned — the switch in messages_render_hook() has no case for
     *      'cart-block' or 'checkout-block', so block pages never get a message wrapper
     *      from this module
     *
     * This pins, on its own, the second of should_load_messages()'s two independent
     * block exclusions (see should_load_on_current_page()'s docblock and
     * testShouldLoadOnCurrentPageInBlockContextsIsUnaffectedByMessagingEligibility()).
     * The first exclusion — should_load_messages()'s own is_block_context() early-out —
     * has no equivalent standalone test here: any test of should_load_on_current_page()
     * still routes through the real messages_render_hook(), so removing the
     * is_block_context() check alone would still be masked by this second exclusion.
     * Isolating it would require a testable subclass overriding messages_render_hook(),
     * which is out of scope for this fix.
     *
     * @dataProvider blockContextProvider
     */
    public function testMessagesRenderHookReturnsNullForBlockLocations(string $location): void
    {
        $this->context->shouldReceive('location')->andReturn($location);

        $testee = $this->createTestee();

        $this->assertNull($testee->messages_render_hook());
    }

    /**
     * GIVEN a merchant has overridden the per-location message renderer hook and priority
     * WHEN resolving the message render hook
     * THEN the overridden values are returned
     * AND the pay-now location uses the 'pay_order' filter name segment, not 'pay-now'
     *
     * @dataProvider renderHookFilterProvider
     */
    public function testMessagesRenderHookIsOverriddenByPerLocationFilters(string $location, string $filter_segment): void
    {
        $this->context->shouldReceive('location')->andReturn($location);

        expectApplied("woocommerce_paypal_payments_{$filter_segment}_messages_renderer_hook")
            ->once()
            ->andReturn('custom_hook');
        expectApplied("woocommerce_paypal_payments_{$filter_segment}_messages_renderer_priority")
            ->once()
            ->andReturn(99);

        $testee = $this->createTestee();
        $hook   = $testee->messages_render_hook();

        $this->assertSame(['name' => 'custom_hook', 'priority' => 99], $hook);
    }

    public function renderHookFilterProvider(): array
    {
        return [
            'checkout uses the checkout filter segment'          => ['checkout', 'checkout'],
            'cart uses the cart filter segment'                  => ['cart', 'cart'],
            'product uses the product filter segment'            => ['product', 'product'],
            'pay-now uses the pay_order filter segment, not pay-now' => ['pay-now', 'pay_order'],
        ];
    }

    /**
     * GIVEN the cart or product default message hook, with a merchant override of the
     *       corresponding button relocation filter
     * WHEN resolving the message render hook, with no messaging-specific hook override
     * THEN the relocated button hook is used as the message hook's default, keeping the
     *      message attached to a button the merchant moved
     *
     * @dataProvider relocatedButtonHookProvider
     */
    public function testMessagesRenderHookDefaultUsesRelocatedButtonHookFirst(string $location, string $relocation_filter, string $relocated_hook): void
    {
        $this->context->shouldReceive('location')->andReturn($location);

        expectApplied($relocation_filter)->once()->andReturn($relocated_hook);

        $testee = $this->createTestee();
        $hook   = $testee->messages_render_hook();

        $this->assertSame($relocated_hook, $hook['name']);
    }

    public function relocatedButtonHookProvider(): array
    {
        return [
            'cart passes through the proceed-to-checkout button relocation filter first' => [
                'cart', 'woocommerce_paypal_payments_proceed_to_checkout_button_renderer_hook', 'my_custom_proceed_hook',
            ],
            'product passes through the single-product button relocation filter first' => [
                'product', 'woocommerce_paypal_payments_single_product_renderer_hook', 'my_custom_product_hook',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // render_message_wrapper()
    // -------------------------------------------------------------------------

    /**
     * GIVEN a page location this module places a message on
     * WHEN the message wrapper is rendered
     * THEN the .ppcp-messages wrapper is echoed between the location's
     *      "before" and "after" actions, in that order
     * AND the pay-now location uses the 'pay_order' action name segment, not 'pay-now'
     *
     * @dataProvider renderMessageWrapperProvider
     */
    public function testRenderMessageWrapperEchoesWrapperBetweenBeforeAndAfterActions(string $location, string $action_segment): void
    {
        $this->context->shouldReceive('location')->andReturn($location);

        $order = [];
        expectDone("ppcp_before_{$action_segment}_message_wrapper")
            ->once()
            ->whenHappen(function () use (&$order): void {
                $order[] = 'before';
            });
        expectDone("ppcp_after_{$action_segment}_message_wrapper")
            ->once()
            ->whenHappen(function () use (&$order): void {
                $order[] = 'after';
            });

        $testee = $this->createTestee();

        ob_start();
        $testee->render_message_wrapper();
        $output = ob_get_clean();

        $this->assertSame('<div class="ppcp-messages"></div>', $output);
        $this->assertSame(['before', 'after'], $order);
    }

    public function renderMessageWrapperProvider(): array
    {
        return [
            'checkout'                        => ['checkout', 'checkout'],
            'cart'                             => ['cart', 'cart'],
            'product'                          => ['product', 'product'],
            'pay-now uses pay_order segment'  => ['pay-now', 'pay_order'],
        ];
    }

    // -------------------------------------------------------------------------
    // enqueue()
    // -------------------------------------------------------------------------

    /**
     * GIVEN the v6 SDK should load on the current classic checkout page
     * WHEN the bootstrap script is enqueued
     * THEN it is registered with the dependencies and version webpack recorded for the
     *      compiled bundle, not an empty dependency list and not just the plugin version.
     */
    public function testEnqueueRegistersScriptWithAssetDataDependenciesAndVersion(): void
    {
        $this->context->shouldReceive('context')->andReturn('checkout');
        $this->context->shouldReceive('location')->andReturn('checkout');
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->card_payments_configuration->shouldReceive('gateway_title')->andReturn('Credit Card');
        $this->card_payments_configuration->shouldReceive('show_name_on_card')->andReturn('no');
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('checkout')->andReturn(true);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('mini-cart')->andReturn(false);
        $this->session_handler->shouldReceive('order')->andReturn(null);
        $this->context->shouldReceive('is_paypal_continuation')->andReturn(false);
        $this->environment->shouldReceive('is_sandbox')->andReturn(false);
        $this->style_mapper->shouldReceive('styles_for_context')->andReturn([]);

        when('WC')->justReturn($this->create_wc_stub());
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('is_product')->justReturn(false);
        when('rest_url')->justReturn('https://example.com/wp-json/wc/store/v1/cart');
        when('wc_get_checkout_url')->justReturn('https://example.com/checkout');

        $this->asset_getter->shouldReceive('get_asset_url')
            ->with('boot.js')
            ->andReturn('https://example.com/assets/boot.js');
        $this->asset_getter->shouldReceive('get_asset_data')
            ->with('boot.js', '1.0.0')
            ->andReturn(['dependencies' => ['wp-data'], 'version' => 'deadbeef']);

        expect('wp_register_script')
            ->once()
            ->with(
                'wc-ppcp-sdk-v6-boot',
                'https://example.com/assets/boot.js',
                ['wp-data'],
                'deadbeef',
                true
            );
        expect('wp_localize_script')->once();
        expect('wp_enqueue_script')->once()->with('wc-ppcp-sdk-v6-boot');

        $testee = $this->createTestee();
        $testee->enqueue();

        $this->addToAssertionCount(1);
    }

    /**
     * GIVEN the v6 SDK does not load on the current page
     * WHEN enqueue() is called
     * THEN no script is registered — the method returns before touching the asset getter
     */
    public function testEnqueueDoesNothingWhenShouldNotLoadOnCurrentPage(): void
    {
        $this->context->shouldReceive('context')->andReturn('');
        $this->context->shouldReceive('location')->andReturn('');
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);
        $this->messages_eligibility->shouldReceive('is_enabled_for_location')->with('')->andReturn(false);

        expect('wp_register_script')->never();

        $testee = $this->createTestee();
        $testee->enqueue();

        $this->addToAssertionCount(1);
    }

    /**
     * GIVEN the current page resolves to no supported page context (e.g. the shop
     *       or home page)
     * WHEN checking whether Fastlane is enabled for the current page
     * THEN it is reported as disabled without asking FastlaneConfig, since there is
     *      no context to evaluate
     */
    public function testIsFastlaneEnabledGuardsAgainstEmptyPageContext(): void
    {
        $this->context->shouldReceive('context')->andReturn('');
        $this->fastlane_config->shouldReceive('should_render')->never();

        $testee = $this->createTestee();

        $this->assertFalse($testee->is_fastlane_enabled());
    }

    /**
     * GIVEN Fastlane's own configuration allows or refuses it for the current page
     * WHEN the SDK bootstrap data is generated
     * THEN the fastlane subtree carries a matching enabled flag and the ppcp-axo
     *      gateway id the ppcp-axo modules register under
     *
     * @dataProvider fastlane_enablement_provider
     */
    public function testScriptDataIncludesFastlaneSubtree(bool $should_render, bool $expected_enabled): void
    {
        $this->context->shouldReceive('context')->andReturn('checkout');
        $this->fastlane_config->shouldReceive('should_render')->with('checkout')->andReturn($should_render);

        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->card_payments_configuration->shouldReceive('gateway_title')->andReturn('Credit Card');
        $this->card_payments_configuration->shouldReceive('show_name_on_card')->andReturn('no');

        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);
        $this->session_handler->shouldReceive('order')->andReturn(null);
        $this->context->shouldReceive('is_paypal_continuation')->andReturn(false);
        $this->environment->shouldReceive('is_sandbox')->andReturn(false);
        $this->style_mapper->shouldReceive('styles_for_context')->andReturn([]);

        when('WC')->justReturn($this->create_wc_stub());
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('is_product')->justReturn(false);
        when('rest_url')->justReturn('https://example.com/wp-json/wc/store/v1/cart');
        when('wc_get_checkout_url')->justReturn('https://example.com/checkout');

        $testee = $this->createTestee();
        $data   = $testee->script_data();

        $this->assertSame(
            ['enabled' => $expected_enabled, 'payment_method' => 'ppcp-axo-gateway'],
            $data['fastlane']
        );
    }
}
