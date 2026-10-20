<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\FreeTrialSubscriptionHelper;

use function Brain\Monkey\Filters\expectApplied;
use function Brain\Monkey\Functions\when;

class MessagesEligibilityTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const RENDER_FILTER = 'woocommerce_paypal_payments_should_render_pay_later_messaging';

    private $settings_provider;
    private $settings_status;
    private $messages_apply;
    private $free_trial_helper;

    public function setUp(): void
    {
        parent::setUp();

        $this->settings_provider = Mockery::mock(SettingsProvider::class);
        $this->settings_status   = Mockery::mock(SettingsStatus::class);
        $this->messages_apply    = Mockery::mock(MessagesApply::class);
        $this->free_trial_helper = Mockery::mock(FreeTrialSubscriptionHelper::class);
    }

    private function createTestee(): MessagesEligibility
    {
        return new MessagesEligibility(
            $this->settings_provider,
            $this->settings_status,
            $this->messages_apply,
            $this->free_trial_helper
        );
    }

    /**
     * Stubs every condition in the eligibility chain to pass, so a single override
     * isolates the one condition under test.
     */
    private function stubHappyPath(array $overrides = []): void
    {
        expectApplied(self::RENDER_FILTER)->andReturn($overrides['render_filter'] ?? true);

        $this->settings_provider
            ->shouldReceive('gateway_enabled')
            ->with(PayPalGateway::ID)
            ->andReturn($overrides['gateway_enabled'] ?? true);

        $this->settings_status
            ->shouldReceive('is_pay_later_messaging_enabled')
            ->andReturn($overrides['messaging_enabled'] ?? true);

        $this->settings_status
            ->shouldReceive('has_pay_later_messaging_locations')
            ->andReturn($overrides['has_locations'] ?? true);

        $this->messages_apply
            ->shouldReceive('for_country')
            ->andReturn($overrides['for_country'] ?? true);

        $this->free_trial_helper
            ->shouldReceive('is_free_trial_cart')
            ->andReturn($overrides['free_trial_cart'] ?? false);

        $this->settings_status
            ->shouldReceive('is_pay_later_messaging_enabled_for_location')
            ->andReturn($overrides['enabled_for_location'] ?? true);
    }

    /**
     * GIVEN an empty messaging settings location
     * WHEN checking whether Pay Later messaging is enabled for that location
     * THEN it is not enabled, and none of the other eligibility checks are consulted
     */
    public function testReturnsFalseForEmptyLocationWithoutCheckingAnythingElse(): void
    {
        expectApplied(self::RENDER_FILTER)->never();
        $this->settings_provider->shouldNotReceive('gateway_enabled');

        $result = $this->createTestee()->is_enabled_for_location('');

        $this->assertFalse($result);
    }

    /**
     * GIVEN pay later messaging would otherwise be fully eligible for a location
     * WHEN a single condition in the eligibility chain fails
     * THEN messaging is not enabled for that location
     * AND WHEN every condition in the chain passes, including the location's own flag
     * THEN messaging is enabled for that location
     *
     * @dataProvider eligibilityChainData
     */
    public function testEligibilityChain(array $overrides, bool $expected): void
    {
        $this->stubHappyPath($overrides);

        $result = $this->createTestee()->is_enabled_for_location('checkout');

        $this->assertSame($expected, $result);
    }

    public function eligibilityChainData(): array
    {
        return [
            'the shared should-render filter disables messaging entirely' => [
                ['render_filter' => false], false,
            ],
            'the PayPal gateway itself is disabled'                       => [
                ['gateway_enabled' => false], false,
            ],
            'pay later messaging is switched off in the settings'         => [
                ['messaging_enabled' => false], false,
            ],
            'no locations have pay later messaging configured'            => [
                ['has_locations' => false], false,
            ],
            'the buyer country is not eligible for pay later messaging'   => [
                ['for_country' => false], false,
            ],
            'the cart is a free trial subscription'                      => [
                ['free_trial_cart' => true], false,
            ],
            'every condition passes and the location itself is enabled'  => [
                [], true,
            ],
        ];
    }

    /**
     * GIVEN a product page with a product currently being viewed
     * WHEN checking whether Pay Later messaging is hidden for the product context
     * THEN the product-specific filter is applied
     * AND it receives the product and its raw price as the filter context data
     */
    public function testIsHiddenForProductContextAppliesProductFilterWithContextData(): void
    {
        $product = Mockery::mock(\WC_Product::class);
        $product->shouldReceive('get_price')->with('raw')->andReturn('19.99');
        when('wc_get_product')->justReturn($product);

        expectApplied('woocommerce_paypal_payments_product_buttons_paylater_disabled')
            ->once()
            ->with(
                false,
                Mockery::on(function (array $context) use ($product): bool {
                    return $context['product'] === $product && $context['order_total'] === 19.99;
                })
            )
            ->andReturn(true);

        $result = $this->createTestee()->is_hidden('product');

        $this->assertTrue($result);
    }

    /**
     * GIVEN a non-product page context (e.g. cart or checkout)
     * WHEN checking whether Pay Later messaging is hidden for that context
     * THEN the generic filter is applied with the context string as its second argument
     */
    public function testIsHiddenForNonProductContextAppliesGenericFilterWithContextString(): void
    {
        expectApplied('woocommerce_paypal_payments_buttons_paylater_disabled')
            ->once()
            ->with(false, 'cart')
            ->andReturn(true);

        $result = $this->createTestee()->is_hidden('cart');

        $this->assertTrue($result);
    }

    /**
     * GIVEN no merchant filter intervenes for either a product or a non-product context
     * WHEN checking whether Pay Later messaging is hidden
     * THEN it defaults to not hidden in both cases
     */
    public function testIsHiddenDefaultsToFalseWhenNoFilterIntervenes(): void
    {
        when('wc_get_product')->justReturn(null);

        $this->assertFalse($this->createTestee()->is_hidden('product'));
        $this->assertFalse($this->createTestee()->is_hidden('checkout'));
    }
}
