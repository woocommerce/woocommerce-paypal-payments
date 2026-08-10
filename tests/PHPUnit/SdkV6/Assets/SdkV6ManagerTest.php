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
    }

    private function createTestee(): SdkV6Manager
    {
        return new SdkV6Manager(
            $this->asset_getter,
            '1.0.0',
            $this->environment,
            $this->style_mapper,
            false,
            $this->settings_status,
            $this->context,
            $this->session_handler,
            $this->cancel_view,
            false,
            false,
            $this->card_payments_configuration
        );
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
        ];
    }

    /**
     * GIVEN a checkout block page with Advanced Card Fields enabled for the merchant
     * WHEN the SDK bootstrap data is generated
     * THEN card_fields.enabled is true
     * AND the gateway title and name-field flag are carried into the payload
     *
     * @dataProvider script_data_card_fields_provider
     */
    public function testScriptDataCardFields(
        string $page_context,
        bool $acdc_enabled,
        string $gateway_title,
        string $show_name_on_card,
        bool $expected_enabled,
        bool $expected_name_field
    ): void {
        $this->context->shouldReceive('context')->andReturn($page_context);
        $this->card_payments_configuration->shouldReceive('is_acdc_enabled')->andReturn($acdc_enabled);
        $this->card_payments_configuration->shouldReceive('gateway_title')->andReturn($gateway_title);
        $this->card_payments_configuration->shouldReceive('show_name_on_card')->andReturn($show_name_on_card);

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

        $testee = $this->createTestee();
        $data   = $testee->script_data();

        $this->assertSame($expected_enabled, $data['card_fields']['enabled']);
        $this->assertSame($gateway_title, $data['card_fields']['title']);
        $this->assertSame($expected_name_field, $data['card_fields']['name_field']);
        $this->assertSame(CreditCardGateway::ID, $data['card_fields']['payment_method']);
    }

    public function script_data_card_fields_provider(): array
    {
        return [
            'checkout-block with ACDC enabled and name field shown' => [
                'checkout-block', true, 'Credit Card', 'yes', true, true,
            ],
            'checkout-block with ACDC enabled and name field hidden' => [
                'checkout-block', true, 'Credit Card', 'no', true, false,
            ],
            'checkout-block with ACDC disabled' => [
                'checkout-block', false, 'Credit Card', 'yes', false, true,
            ],
            'classic checkout with ACDC enabled' => [
                'checkout', true, 'Credit Card', 'yes', true, true,
            ],
        ];
    }
}
