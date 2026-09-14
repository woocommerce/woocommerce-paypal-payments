<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Assets;

use Mockery;
use ReflectionClass;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreatePaymentToken;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Endpoint\CreateSetupToken;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\SdkV6\Helper\CardFieldStyles;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class AddPaymentMethodManagerTest extends TestCase
{
    private $asset_getter;
    private $environment;
    private $context;
    private $settings_provider;
    private $card_field_styles;

    public function setUp(): void
    {
        parent::setUp();

        $this->asset_getter = Mockery::mock(AssetGetter::class);
        $this->environment = Mockery::mock(Environment::class);
        $this->context = Mockery::mock(Context::class);
        $this->settings_provider = Mockery::mock(SettingsProvider::class);
        $this->card_field_styles = Mockery::mock(CardFieldStyles::class);
        $this->card_field_styles->shouldReceive('overrides')->andReturn([])->byDefault();
    }

    private function createTestee(
        bool $paypal_vaulting_enabled = false,
        bool $card_vaulting_enabled = false
    ): AddPaymentMethodManager {
        return new AddPaymentMethodManager(
            $this->asset_getter,
            '1.0.0',
            $this->environment,
            $this->context,
            $paypal_vaulting_enabled,
            $card_vaulting_enabled,
            $this->settings_provider,
            $this->card_field_styles
        );
    }

    /**
     * GIVEN a buyer's login state, vaulting configuration and page context
     * WHEN checking whether the add-payment-method surfaces should load
     * THEN the result is true only when the buyer is logged in, at least one
     *      vaulting flag is enabled, and the current page is the add-payment-method page
     *
     * @dataProvider should_load_provider
     */
    public function testShouldLoadOnCurrentPage(
        bool $is_logged_in,
        bool $paypal_vaulting_enabled,
        bool $card_vaulting_enabled,
        bool $is_add_payment_method_page,
        bool $expected
    ): void {
        when('is_user_logged_in')->justReturn($is_logged_in);
        $this->context->shouldReceive('is_add_payment_method_page')
            ->andReturn($is_add_payment_method_page)
            ->byDefault();

        $testee = $this->createTestee($paypal_vaulting_enabled, $card_vaulting_enabled);

        $this->assertSame($expected, $testee->should_load_on_current_page());
    }

    public function should_load_provider(): array
    {
        return [
            'logged out never loads regardless of vaulting or page' => [false, true, true, true, false],
            'logged in but no vaulting enabled does not load' => [true, false, false, true, false],
            'logged in with paypal vaulting but wrong page does not load' => [true, true, false, false, false],
            'logged in with card vaulting on the add payment method page loads' => [true, false, true, true, true],
            'logged in with paypal vaulting on the add payment method page loads' => [true, true, false, true, true],
        ];
    }

    /**
     * GIVEN a merchant with card vaulting configured and a 3DS contingency filter applied
     * WHEN the add-payment-method bootstrap script data is generated
     * THEN the button, card fields and ajax endpoint data reflect the constructor configuration
     * AND the verification method reflects the filtered 3D Secure setting
     * AND the merchant's card field style overrides are carried into the payload
     *
     * @dataProvider script_data_provider
     */
    public function testScriptData(bool $card_vaulting_enabled): void
    {
        $this->environment->shouldReceive('is_sandbox')->andReturn(false);
        $this->settings_provider->shouldReceive('three_d_secure_enum')->andReturn('SCA_ALWAYS');

        when('apply_filters')->alias(
            static function (string $filter, $value) {
                if ('woocommerce_paypal_payments_three_d_secure_contingency' === $filter) {
                    return 'SCA_WHEN_REQUIRED';
                }
                return $value;
            }
        );
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('wc_get_account_endpoint_url')->justReturn('https://example.com/my-account/payment-methods');
        when('wp_create_nonce')->alias(static fn (string $action) => 'nonce-' . $action);

        $card_field_styles = ['fontSize' => '18px'];
        $this->card_field_styles->shouldReceive('overrides')->andReturn($card_field_styles);

        $testee = $this->createTestee(false, $card_vaulting_enabled);
        $data   = $this->invoke_script_data($testee);

        $this->assertSame('#' . AddPaymentMethodManager::WRAPPER_ID, $data['button']['wrapper']);
        $this->assertSame('paypal-gold', $data['button']['color_class']);

        $this->assertSame($card_vaulting_enabled, $data['card_fields']['enabled']);
        $this->assertSame(CreditCardGateway::ID, $data['card_fields']['payment_method']);
        $this->assertSame($card_field_styles, $data['card_fields']['styles']);

        $this->assertArrayHasKey('client_token', $data['ajax']);
        $this->assertArrayHasKey('create_setup_token', $data['ajax']);
        $this->assertArrayHasKey('create_payment_token', $data['ajax']);

        $this->assertSame(
            ClientTokenEndpoint::ENDPOINT,
            $data['ajax']['client_token']['endpoint']
        );
        $this->assertSame(
            'nonce-' . ClientTokenEndpoint::nonce(),
            $data['ajax']['client_token']['nonce']
        );

        $this->assertSame(
            CreateSetupToken::ENDPOINT,
            $data['ajax']['create_setup_token']['endpoint']
        );
        $this->assertSame(
            'nonce-' . CreateSetupToken::nonce(),
            $data['ajax']['create_setup_token']['nonce']
        );

        $this->assertSame(
            CreatePaymentToken::ENDPOINT,
            $data['ajax']['create_payment_token']['endpoint']
        );
        $this->assertSame(
            'nonce-' . CreatePaymentToken::nonce(),
            $data['ajax']['create_payment_token']['nonce']
        );

        $this->assertSame('SCA_WHEN_REQUIRED', $data['verification_method']);
    }

    public function script_data_provider(): array
    {
        return [
            'card vaulting enabled surfaces card fields as enabled' => [true],
            'card vaulting disabled surfaces card fields as disabled' => [false],
        ];
    }

    /**
     * Invokes the private script_data() method via reflection, since it is
     * only reachable indirectly through enqueue() in production code.
     */
    private function invoke_script_data(AddPaymentMethodManager $testee): array
    {
        $method = (new ReflectionClass($testee))->getMethod('script_data');
        $method->setAccessible(true);

        return $method->invoke($testee);
    }

    // -------------------------------------------------------------------------
    // enqueue()
    // -------------------------------------------------------------------------

    /**
     * GIVEN a logged-in buyer with vaulting enabled on the add-payment-method page
     * WHEN the bootstrap script is enqueued
     * THEN it is registered with the dependencies and version webpack recorded for the
     *      compiled bundle, not an empty dependency list and not just the plugin version.
     */
    public function testEnqueueRegistersScriptWithAssetDataDependenciesAndVersion(): void
    {
        when('is_user_logged_in')->justReturn(true);
        $this->context->shouldReceive('is_add_payment_method_page')->andReturn(true);

        $this->environment->shouldReceive('is_sandbox')->andReturn(false);
        $this->settings_provider->shouldReceive('three_d_secure_enum')->andReturn('SCA_ALWAYS');
        when('apply_filters')->alias(static fn (string $filter, $value) => $value);
        when('get_woocommerce_currency')->justReturn('USD');
        when('get_locale')->justReturn('en_US');
        when('wc_get_account_endpoint_url')->justReturn('https://example.com/my-account/payment-methods');
        when('wp_create_nonce')->justReturn('nonce');
        when('wp_register_style')->justReturn(true);
        when('wp_enqueue_style')->justReturn(null);
        when('wp_add_inline_style')->justReturn(null);

        $this->asset_getter->shouldReceive('get_asset_url')
            ->with('boot-add-payment-method.js')
            ->andReturn('https://example.com/assets/boot-add-payment-method.js');
        $this->asset_getter->shouldReceive('get_asset_data')
            ->with('boot-add-payment-method.js', '1.0.0')
            ->andReturn(['dependencies' => ['wp-data'], 'version' => 'deadbeef']);

        expect('wp_register_script')
            ->once()
            ->with(
                'wc-ppcp-sdk-v6-add-payment-method',
                'https://example.com/assets/boot-add-payment-method.js',
                ['wp-data'],
                'deadbeef',
                true
            );
        expect('wp_localize_script')->once();
        expect('wp_enqueue_script')->once()->with('wc-ppcp-sdk-v6-add-payment-method');

        $testee = $this->createTestee(true, true);
        $testee->enqueue();

        $this->addToAssertionCount(1);
    }

    /**
     * GIVEN the add-payment-method surfaces should not load on the current page
     * WHEN enqueue() is called
     * THEN no script is registered — the method returns before touching the asset getter
     */
    public function testEnqueueDoesNothingWhenShouldNotLoadOnCurrentPage(): void
    {
        when('is_user_logged_in')->justReturn(false);

        expect('wp_register_script')->never();

        $testee = $this->createTestee(true, true);
        $testee->enqueue();

        $this->addToAssertionCount(1);
    }
}
