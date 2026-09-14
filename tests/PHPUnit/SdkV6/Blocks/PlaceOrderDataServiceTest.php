<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6\Blocks;

use Mockery;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;

use function Brain\Monkey\Filters\expectApplied;

/**
 * Exercises the 'sdk-v6.blocks.place-order-data' service exactly as it is defined in
 * modules/ppcp-sdk-v6/services.php: the container closure is loaded from the real
 * production file and invoked against a stub container, rather than re-implemented
 * here, so a future edit to that file is what these tests actually protect.
 *
 * V6PaymentMethod exposes the resolved array as the "place_order" entry of its
 * payment method data, deciding whether the v6 block checkout offers the
 * non-express PayPal row (the standard "Place order" button).
 */
class PlaceOrderDataServiceTest extends TestCase
{
    private const FILTER = 'woocommerce_paypal_payments_blocks_add_place_order_method';

    /**
     * Resolves the 'sdk-v6.blocks.place-order-data' callable from the real
     * services.php, backed by a container serving the given config plus sane
     * defaults for everything the factory reads.
     */
    private function resolveService(
        array $config,
        SettingsProvider $settings_provider,
        SubscriptionHelper $subscription_helper
    ): callable {
        $values = array_merge(
            [
                'settings.settings-provider' => $settings_provider,
                'wc-subscriptions.helper' => $subscription_helper,
                'wcgateway.place-order-button-text' => 'Place order',
                'wcgateway.place-order-button-description' => 'Pay with PayPal.',
                'button.client_id' => '',
            ],
            $config
        );

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')
            ->andReturnUsing(static function (string $key) use ($values) {
                return $values[$key];
            });

        $services = require ROOT_DIR . '/modules/ppcp-sdk-v6/services.php';

        $factory = $services['sdk-v6.blocks.place-order-data'];

        return $factory($container);
    }

    private function settingsProviderThatCanVault(bool $can_save): SettingsProvider
    {
        $settings_provider = Mockery::mock(SettingsProvider::class);
        $settings_provider->shouldReceive('save_paypal_and_venmo')->andReturn($can_save);

        return $settings_provider;
    }

    private function subscriptionHelperWithCart(bool $has_subscription): SubscriptionHelper
    {
        $subscription_helper = Mockery::mock(SubscriptionHelper::class);
        $subscription_helper->shouldReceive('cart_contains_subscription')->andReturn($has_subscription);

        return $subscription_helper;
    }

    /**
     * GIVEN a non-subscription cart and no filter callback overriding the default
     * WHEN the place-order data provider is resolved
     * THEN it reports the row enabled, with the configured button text and description
     */
    public function testHappyPathOffersThePlaceOrderRowWithConfiguredCopy(): void
    {
        expectApplied(self::FILTER)->once()->with(true)->andReturnFirstArg();

        $place_order_data = $this->resolveService(
            [
                'wcgateway.place-order-button-text' => 'Pay via PayPal',
                'wcgateway.place-order-button-description' => 'Redirects to PayPal to complete payment.',
            ],
            $this->settingsProviderThatCanVault(false),
            $this->subscriptionHelperWithCart(false)
        );

        $this->assertSame(
            [
                'enabled' => true,
                'text' => 'Pay via PayPal',
                'description' => 'Redirects to PayPal to complete payment.',
            ],
            $place_order_data()
        );
    }

    /**
     * GIVEN no callback has hooked the filter
     * WHEN the place-order data provider is resolved
     * THEN the filter's own default (true) is what decides the outcome, so the row
     *      is enabled
     */
    public function testFilterDefaultsToTrueWhenNoCallbackIsRegistered(): void
    {
        expectApplied(self::FILTER)->once()->with(true)->andReturnFirstArg();

        $place_order_data = $this->resolveService(
            [],
            $this->settingsProviderThatCanVault(false),
            $this->subscriptionHelperWithCart(false)
        );

        $this->assertTrue($place_order_data()['enabled']);
    }

    /**
     * GIVEN a callback that switches the filter off
     * WHEN the place-order data provider is invoked
     * THEN the row is disabled
     */
    public function testFilterCallbackDisablesTheRow(): void
    {
        expectApplied(self::FILTER)->once()->with(true)->andReturn(false);

        $place_order_data = $this->resolveService(
            [],
            $this->settingsProviderThatCanVault(false),
            $this->subscriptionHelperWithCart(false)
        );

        $this->assertFalse($place_order_data()['enabled']);
    }

    /**
     * GIVEN the place-order data provider was already resolved from the container
     * WHEN a callback that switches the filter off is registered only afterwards,
     *      and the provider is then invoked
     * THEN the row is disabled, proving the filter is read inside the returned
     *      closure at call time, not captured once when the container was built
     */
    public function testFilterCallbackRegisteredAfterResolutionIsStillHonoured(): void
    {
        $place_order_data = $this->resolveService(
            [],
            $this->settingsProviderThatCanVault(false),
            $this->subscriptionHelperWithCart(false)
        );

        // Registered only now, after the closure above already exists.
        expectApplied(self::FILTER)->once()->with(true)->andReturn(false);

        $this->assertFalse($place_order_data()['enabled']);
    }

    /**
     * GIVEN a cart containing a subscription, with the filter defaulting to enabled
     * WHEN whether the saved payment method could vault the renewals is varied
     * THEN the row is only enabled when both the "save PayPal and Venmo" setting is
     *      on and a client ID is configured; any single missing piece disables it
     *
     * @dataProvider vaulting_capability_provider
     */
    public function testSubscriptionCartOnlyEnabledWhenRenewalsCanBeVaulted(
        bool $save_paypal_and_venmo,
        string $client_id,
        bool $expected_enabled
    ): void {
        expectApplied(self::FILTER)->once()->with(true)->andReturnFirstArg();

        $place_order_data = $this->resolveService(
            ['button.client_id' => $client_id],
            $this->settingsProviderThatCanVault($save_paypal_and_venmo),
            $this->subscriptionHelperWithCart(true)
        );

        $this->assertSame($expected_enabled, $place_order_data()['enabled']);
    }

    public function vaulting_capability_provider(): array
    {
        return [
            'cannot vault: neither setting nor client id' => [false, '', false],
            'cannot vault: setting on but no client id' => [true, '', false],
            'cannot vault: client id set but setting off' => [false, 'client-123', false],
            'can vault: both setting and client id present' => [true, 'client-123', true],
        ];
    }

    /**
     * GIVEN a cart without a subscription, and the merchant unable to vault (no
     *       "save PayPal and Venmo" setting, no client ID)
     * WHEN the place-order data provider is resolved
     * THEN the row is still enabled, since the vaulting gate only applies to
     *      subscription carts
     */
    public function testNonSubscriptionCartIsUnaffectedByVaultingConditions(): void
    {
        expectApplied(self::FILTER)->once()->with(true)->andReturnFirstArg();

        $place_order_data = $this->resolveService(
            ['button.client_id' => ''],
            $this->settingsProviderThatCanVault(false),
            $this->subscriptionHelperWithCart(false)
        );

        $this->assertTrue($place_order_data()['enabled']);
    }

    /**
     * GIVEN the place-order data provider was already resolved from the container
     * WHEN the button text and description services answer differently on a second
     *      call
     * THEN each call returns the copy current at that moment, proving both are read
     *      inside the closure rather than captured once at resolution time
     */
    public function testTextAndDescriptionAreResolvedPerCall(): void
    {
        expectApplied(self::FILTER)->twice()->with(true)->andReturnFirstArg();

        $settings_provider = $this->settingsProviderThatCanVault(false);
        $subscription_helper = $this->subscriptionHelperWithCart(false);

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with('settings.settings-provider')->andReturn($settings_provider);
        $container->shouldReceive('get')->with('wc-subscriptions.helper')->andReturn($subscription_helper);
        $container->shouldReceive('get')->with('button.client_id')->andReturn('');
        $container->shouldReceive('get')->with('wcgateway.place-order-button-text')
            ->andReturn('First text', 'Second text');
        $container->shouldReceive('get')->with('wcgateway.place-order-button-description')
            ->andReturn('First description', 'Second description');

        $services = require ROOT_DIR . '/modules/ppcp-sdk-v6/services.php';
        $place_order_data = $services['sdk-v6.blocks.place-order-data']($container);

        $first = $place_order_data();
        $second = $place_order_data();

        $this->assertSame('First text', $first['text']);
        $this->assertSame('First description', $first['description']);
        $this->assertSame('Second text', $second['text']);
        $this->assertSame('Second description', $second['description']);
    }

    /**
     * GIVEN a cart that gains a subscription between two requests, with the merchant
     *       unable to vault renewals, so an enabled subscription cart must flip the
     *       answer
     * WHEN the same resolved provider is invoked before and after that change
     * THEN the first call reports the row enabled and the second reports it
     *      disabled, proving the callable re-evaluates the cart on every call
     *      rather than caching its first answer
     */
    public function testReEvaluatesCartStateOnEachInvocation(): void
    {
        expectApplied(self::FILTER)->twice()->with(true)->andReturnFirstArg();

        $subscription_helper = Mockery::mock(SubscriptionHelper::class);
        $subscription_helper->shouldReceive('cart_contains_subscription')
            ->andReturn(false, true);

        $place_order_data = $this->resolveService(
            ['button.client_id' => ''],
            $this->settingsProviderThatCanVault(false),
            $subscription_helper
        );

        $this->assertTrue($place_order_data()['enabled']);
        $this->assertFalse($place_order_data()['enabled']);
    }
}
