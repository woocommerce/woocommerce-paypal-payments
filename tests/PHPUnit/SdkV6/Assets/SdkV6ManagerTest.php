<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Assets;

use Mockery;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ApplePayConfig;
use WooCommerce\PayPalCommerce\SdkV6\Helper\ButtonStyleMapper;
use WooCommerce\PayPalCommerce\SdkV6\Helper\GooglePayConfig;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelView;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
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
    private $subscription_helper;
	private $credit_card_icons;
	private $google_pay_config;
	private $apple_pay_config;

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
		// Off by default like the wallets: script_data() reads it on every call,
		// and most tests here exercise something other than the card button.
		$this->card_payments_configuration->shouldReceive('is_bcdc_enabled')->andReturn(false)->byDefault();
		$this->subscription_helper = Mockery::mock(SubscriptionHelper::class);
        $this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn(false)->byDefault();
		$this->credit_card_icons = [];

		$this->google_pay_config = Mockery::mock(GooglePayConfig::class);
		$this->google_pay_config->shouldReceive('should_render')->andReturn(false)->byDefault();

		$this->apple_pay_config = Mockery::mock(ApplePayConfig::class);
		$this->apple_pay_config->shouldReceive('should_render')->andReturn(false)->byDefault();
		$this->apple_pay_config->shouldReceive('display_name')->andReturn('Test Store')->byDefault();

		// Reached unconditionally by script_data()'s Apple Pay validation block.
		when('admin_url')->justReturn('https://example.com/wp-admin/admin-ajax.php');
		when('wp_create_nonce')->justReturn('nonce');
    }

    /**
     * A WC() stub with an empty cart/customer and a payment-gateways registry.
     * Defaults to no gateways available, so a scenario that needs one (e.g.
     * CardButtonGateway::ID) names it explicitly.
     */
    private function create_wc_stub(array $available_gateways = []): object
    {
        $payment_gateways = Mockery::mock();
        $payment_gateways->shouldReceive('get_available_payment_gateways')->andReturn($available_gateways)->byDefault();

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
	        $merchant_country,
	        $this->google_pay_config,
	        $this->apple_pay_config
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

    /**
     * GIVEN a page context and a BCDC (Basic Credit and Debit Cards) setting
     * WHEN checking whether the v6 Basic Card button is enabled for that location
     * THEN the result depends on both the page context and the BCDC setting
     * AND checkout-block is never eligible, since BCDC has no block checkout support
     *
     * @dataProvider card_button_enablement_provider
     */
    public function testIsCardButtonEnabled(string $page_context, bool $bcdc_enabled, bool $expected): void
    {
        $this->context->shouldReceive('context')->andReturn($page_context);
        $this->card_payments_configuration->shouldReceive('is_bcdc_enabled')->andReturn($bcdc_enabled);

        $testee = $this->createTestee();

        $this->assertSame($expected, $testee->is_card_button_enabled());
    }

    public function card_button_enablement_provider(): array
    {
        return [
            'classic checkout with BCDC enabled renders the card button' => ['checkout', true, true],
            'classic checkout with BCDC disabled does not render the card button' => ['checkout', false, false],
            'pay-now with BCDC enabled renders the card button' => ['pay-now', true, true],
            'pay-now with BCDC disabled does not render the card button' => ['pay-now', false, false],
            'checkout-block is never eligible, even with BCDC enabled' => ['checkout-block', true, false],
            'product page is never eligible for the card button' => ['product', true, false],
            'cart page is never eligible for the card button' => ['cart', true, false],
        ];
    }

    /**
     * GIVEN BCDC configured for checkout/pay-now, with every smart-button
     *       location and ACDC turned off
     * WHEN checking whether the v6 SDK should load on the current page
     * THEN the SDK loads on checkout and pay-now purely because BCDC is enabled there
     * AND it does not load on product, cart or checkout-block, since BCDC has no
     *     block checkout support and settings alone never enable it elsewhere
     *
     * @dataProvider should_load_for_card_button_provider
     */
    public function testShouldLoadOnCurrentPageForCardButton(string $page_context, bool $bcdc_enabled, bool $expected): void
    {
        $this->context->shouldReceive('context')->andReturn($page_context);
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->card_payments_configuration->shouldReceive('is_bcdc_enabled')->andReturn($bcdc_enabled);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);

        $testee = $this->createTestee();

        $this->assertSame($expected, $testee->should_load_on_current_page());
    }

    public function should_load_for_card_button_provider(): array
    {
        return [
            'checkout with BCDC enabled loads the SDK' => ['checkout', true, true],
            'pay-now with BCDC enabled loads the SDK' => ['pay-now', true, true],
            'checkout with BCDC disabled does not load for the card button' => ['checkout', false, false],
            'product page never loads for the card button' => ['product', true, false],
            'cart page never loads for the card button' => ['cart', true, false],
            'checkout-block never loads for the card button (no block support)' => ['checkout-block', true, false],
        ];
    }

    /**
     * GIVEN a checkout page where BCDC is enabled for the settings-only gate
     * WHEN the SDK bootstrap data is generated
     * THEN card_button.enabled is true only when the CardButtonGateway is also
     *      among WooCommerce's available payment gateways
     * AND it is false whenever the gateway is unavailable — the same signal
     *     WooCommerce gives for ACDC-active-outside-Mexico, a free-trial cart,
     *     or a zero-total cart, since each of those removes the gateway itself
     * AND it is false for a cart containing a subscription, since BCDC is
     *     withheld there regardless of gateway availability
     *
     * @dataProvider card_button_row_provider
     */
    public function testScriptDataCardButtonEnabled(bool $cart_contains_subscription, bool $gateway_available, bool $expected): void
    {
        $this->context->shouldReceive('context')->andReturn('checkout');
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->card_payments_configuration->shouldReceive('is_bcdc_enabled')->andReturn(true);
        $this->card_payments_configuration->shouldReceive('gateway_title')->andReturn('Credit Card');
        $this->card_payments_configuration->shouldReceive('show_name_on_card')->andReturn('no');
        $this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn($cart_contains_subscription);

        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);
        $this->session_handler->shouldReceive('order')->andReturn(null);
        $this->context->shouldReceive('is_paypal_continuation')->andReturn(false);
        $this->environment->shouldReceive('is_sandbox')->andReturn(false);
        $this->style_mapper->shouldReceive('styles_for_context')->andReturn(['borderRadius' => '4px']);

        when('WC')->justReturn($this->create_wc_stub($gateway_available ? [CardButtonGateway::ID => true] : []));
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('is_product')->justReturn(false);
        when('rest_url')->justReturn('https://example.com/wp-json/wc/store/v1/cart');
        when('wc_get_checkout_url')->justReturn('https://example.com/checkout');

        $testee = $this->createTestee();
        $data   = $testee->script_data();

        $this->assertSame($expected, $data['card_button']['enabled']);
    }

    public function card_button_row_provider(): array
    {
        return [
            'gateway available and no subscription renders the row' => [false, true, true],
            'gateway unavailable never renders the row (ACDC-active, free trial, or zero-total cart)' => [false, false, false],
            'subscription cart withholds the row even though the gateway is available' => [true, true, false],
        ];
    }

    /**
     * GIVEN BCDC enabled and the CardButtonGateway available at checkout
     * WHEN the SDK bootstrap data is generated
     * THEN the card_button payload carries the payment method id, funding source,
     *      wrapper selector and button styling alongside its enabled flag
     * AND the front-end labels include a card_declined message for the SDK to
     *     show when a card is declined
     */
    public function testScriptDataCardButtonShapeAndCardDeclinedLabel(): void
    {
        $this->context->shouldReceive('context')->andReturn('checkout');
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn(false);
        $this->card_payments_configuration->shouldReceive('is_bcdc_enabled')->andReturn(true);
        $this->card_payments_configuration->shouldReceive('gateway_title')->andReturn('Credit Card');
        $this->card_payments_configuration->shouldReceive('show_name_on_card')->andReturn('no');

        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')->andReturn(false);
        $this->session_handler->shouldReceive('order')->andReturn(null);
        $this->context->shouldReceive('is_paypal_continuation')->andReturn(false);
        $this->environment->shouldReceive('is_sandbox')->andReturn(false);
        $this->style_mapper->shouldReceive('styles_for_context')->andReturn(['borderRadius' => '8px']);

        when('WC')->justReturn($this->create_wc_stub([CardButtonGateway::ID => true]));
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('is_product')->justReturn(false);
        when('rest_url')->justReturn('https://example.com/wp-json/wc/store/v1/cart');
        when('wc_get_checkout_url')->justReturn('https://example.com/checkout');

        $testee = $this->createTestee();
        $data   = $testee->script_data();

        $this->assertSame(CardButtonGateway::ID, $data['card_button']['payment_method']);
        $this->assertSame('card', $data['card_button']['funding_source']);
        $this->assertSame('#' . SdkV6Manager::CARD_BUTTON_WRAPPER_ID, $data['card_button']['wrapper']);
        $this->assertSame(
            [
                'borderRadius' => '8px',
                'height'       => SdkV6Manager::PAYMENT_BUTTON_HEIGHT,
                'width'        => '100%',
            ],
            $data['card_button']['styles']
        );
        $this->assertSame(
            'The card could not be charged. Please check the details or try a different card.',
            $data['labels']['card_declined']
        );
    }

    /**
     * GIVEN the BCDC row is eligible to print (checkout, BCDC enabled, the
     *       gateway available, no subscription in the cart)
     * WHEN the card button wrapper is rendered
     * THEN exactly one hide-style for the CardButtonGateway payment method
     *      and one wrapper div with the BCDC wrapper id are printed
     */
    public function testRenderCardButtonWrapperPrintsHideStyleAndWrapperWhenRowApplies(): void
    {
        $this->context->shouldReceive('context')->andReturn('checkout');
        $this->card_payments_configuration->shouldReceive('is_bcdc_enabled')->andReturn(true);
        $this->subscription_helper->shouldReceive('cart_contains_subscription')->andReturn(false);

        when('WC')->justReturn($this->create_wc_stub([CardButtonGateway::ID => true]));

        $testee = $this->createTestee();

        ob_start();
        $testee->render_card_button_wrapper();
        $output = ob_get_clean();

        $this->assertSame(1, substr_count($output, "<style data-hide-gateway='" . CardButtonGateway::ID . "'>"));
        $this->assertSame(1, substr_count($output, '<div id="' . SdkV6Manager::CARD_BUTTON_WRAPPER_ID . '"></div>'));
        $this->assertStringContainsString('.wc_payment_method.payment_method_' . CardButtonGateway::ID, $output);
    }

    /**
     * GIVEN a page where the BCDC row does not apply (BCDC disabled for the
     *       current location)
     * WHEN the card button wrapper is rendered
     * THEN nothing is printed
     */
    public function testRenderCardButtonWrapperPrintsNothingWhenRowDoesNotApply(): void
    {
        $this->context->shouldReceive('context')->andReturn('checkout');
        $this->card_payments_configuration->shouldReceive('is_bcdc_enabled')->andReturn(false);

        $testee = $this->createTestee();

        ob_start();
        $testee->render_card_button_wrapper();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    /**
     * GIVEN a buyer on the pay-for-order page, where wallets render as express
     *       buttons rather than as payment-method rows
     * WHEN the SDK bootstrap data is generated
     * THEN each wallet's gateway subtree stays null, since is_wallet_gateway()
     *      checks only for the 'checkout' context and pay-now was deliberately
     *      not added when BCDC gained its own row support
     */
    public function testScriptDataWalletGatewayStaysNullOnPayNow(): void
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

        when('WC')->justReturn($this->create_wc_stub([
            GooglePayGateway::ID => true,
            ApplePayGateway::ID  => true,
        ]));
        when('wc_get_order')->justReturn($order);
        when('wc_get_base_location')->justReturn(['country' => 'US']);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('is_product')->justReturn(false);
        when('rest_url')->justReturn('https://example.com/wp-json/wc/store/v1/cart');
        when('wc_get_checkout_url')->justReturn('https://example.com/checkout');

        $testee = $this->createTestee(true);
        $data   = $testee->script_data();

        $this->assertNull($data['google_pay']['gateway']);
        $this->assertNull($data['apple_pay']['gateway']);
    }
}
