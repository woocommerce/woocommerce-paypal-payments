<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Blocks\ProductBlocks
 */
class ProductBlocksTest extends TestCase
{
    /**
     * GIVEN the feature flag filter is off and the env override is unset
     * WHEN is_feature_enabled() is asked whether the product blocks feature should load
     * THEN it defaults to enabled, since 'PCP_PRODUCT_BLOCKS' only disables when explicitly '0'
     *
     * @dataProvider feature_enabled_provider
     */
    public function testIsFeatureEnabledReflectsTheFeatureFlagAndEnvOverride(
        $env_value,
        bool $filter_result,
        bool $expected
    ): void {
        when('getenv')->justReturn($env_value);
        when('apply_filters')->justReturn($filter_result);

        $this->assertSame($expected, ProductBlocks::is_feature_enabled());
    }

    public function feature_enabled_provider(): array
    {
        return array(
            'env unset, filter reports enabled'      => array(false, true, true),
            'env explicitly disables, filter agrees' => array('0', false, false),
            'filter forces disabled regardless of env' => array(false, false, false),
        );
    }

    /**
     * GIVEN the feature is enabled
     * WHEN is_messaging_enabled() is asked whether the product Pay Later placement is on
     * THEN it reflects the settings status answer for the 'product' location
     *
     * @dataProvider messaging_enabled_provider
     */
    public function testIsMessagingEnabledReflectsSettingsStatusForProductLocation(bool $location_enabled): void
    {
        when('getenv')->justReturn(false);
        when('apply_filters')->justReturn(true);

        $settings_status = Mockery::mock(SettingsStatus::class);
        $settings_status->shouldReceive('is_pay_later_messaging_enabled_for_location')
            ->with('product')
            ->andReturn($location_enabled);

        $this->assertSame($location_enabled, ProductBlocks::is_messaging_enabled($settings_status));
    }

    public function messaging_enabled_provider(): array
    {
        return array(
            'location enabled'  => array(true),
            'location disabled' => array(false),
        );
    }

    /**
     * GIVEN the feature is disabled (filter forces it off)
     * WHEN is_messaging_enabled() is asked, even though the location itself is enabled
     * THEN it reports disabled, since the feature flag gates every product surface
     */
    public function testIsMessagingEnabledIsFalseWhenFeatureIsNotEnabledEvenIfLocationIsEnabled(): void
    {
        when('getenv')->justReturn(false);
        when('apply_filters')->justReturn(false);

        $settings_status = Mockery::mock(SettingsStatus::class);
        $settings_status->shouldReceive('is_pay_later_messaging_enabled_for_location')
            ->with('product')
            ->andReturn(true);

        $this->assertFalse(ProductBlocks::is_messaging_enabled($settings_status));
    }

    /**
     * GIVEN the feature is enabled
     * WHEN is_buttons_enabled() is asked whether the product Smart Buttons placement is on
     * THEN it reflects the settings status answer for the 'product' location
     *
     * @dataProvider buttons_enabled_provider
     */
    public function testIsButtonsEnabledReflectsSettingsStatusForProductLocation(bool $location_enabled): void
    {
        when('getenv')->justReturn(false);
        when('apply_filters')->justReturn(true);

        $settings_status = Mockery::mock(SettingsStatus::class);
        $settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('product')
            ->andReturn($location_enabled);

        $this->assertSame($location_enabled, ProductBlocks::is_buttons_enabled($settings_status));
    }

    public function buttons_enabled_provider(): array
    {
        return array(
            'location enabled'  => array(true),
            'location disabled' => array(false),
        );
    }

    /**
     * GIVEN the feature is disabled (filter forces it off)
     * WHEN is_buttons_enabled() is asked, even though the location itself is enabled
     * THEN it reports disabled, since the feature flag gates every product surface
     */
    public function testIsButtonsEnabledIsFalseWhenFeatureIsNotEnabledEvenIfLocationIsEnabled(): void
    {
        when('getenv')->justReturn(false);
        when('apply_filters')->justReturn(false);

        $settings_status = Mockery::mock(SettingsStatus::class);
        $settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('product')
            ->andReturn(true);

        $this->assertFalse(ProductBlocks::is_buttons_enabled($settings_status));
    }

    /**
     * GIVEN the container does not have the 'sdk-v6.owns-current-page' service registered
     *       (the v6 module is not loaded on this site)
     * WHEN v6_owns_current_page() is asked whether v6 owns the current page
     * THEN it reports false without attempting to resolve the missing service
     */
    public function testV6OwnsCurrentPageIsFalseWhenServiceIsNotRegistered(): void
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('has')->with('sdk-v6.owns-current-page')->andReturn(false);
        $container->shouldNotReceive('get');

        $this->assertFalse(ProductBlocks::v6_owns_current_page($container));
    }

    /**
     * GIVEN the container serves the 'sdk-v6.owns-current-page' predicate
     * WHEN v6_owns_current_page() is asked whether v6 owns the current page
     * THEN it returns exactly what the predicate resolves to
     *
     * @dataProvider owns_current_page_provider
     */
    public function testV6OwnsCurrentPageReflectsTheRegisteredPredicate(bool $owns_current_page): void
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('has')->with('sdk-v6.owns-current-page')->andReturn(true);
        $container->shouldReceive('get')
            ->with('sdk-v6.owns-current-page')
            ->andReturn(static fn (): bool => $owns_current_page);

        $this->assertSame($owns_current_page, ProductBlocks::v6_owns_current_page($container));
    }

    public function owns_current_page_provider(): array
    {
        return array(
            'predicate resolves true'  => array(true),
            'predicate resolves false' => array(false),
        );
    }
}
