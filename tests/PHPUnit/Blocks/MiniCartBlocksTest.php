<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;

/**
 * @covers \WooCommerce\PayPalCommerce\Blocks\MiniCartBlocks
 */
class MiniCartBlocksTest extends TestCase
{
    /**
     * GIVEN a settings status that reports the mini-cart Smart Buttons placement on or off
     * WHEN is_buttons_enabled() is asked whether the mini-cart Smart Buttons placement is on
     * THEN it mirrors the settings status answer for the 'mini-cart' location
     *
     * @dataProvider buttons_enabled_provider
     */
    public function testIsButtonsEnabledReflectsSettingsStatusForMiniCartLocation(bool $location_enabled): void
    {
        $settings_status = Mockery::mock(SettingsStatus::class);
        $settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('mini-cart')
            ->andReturn($location_enabled);

        $this->assertSame($location_enabled, MiniCartBlocks::is_buttons_enabled($settings_status));
    }

    public function buttons_enabled_provider(): array
    {
        return array(
            'location enabled'  => array(true),
            'location disabled' => array(false),
        );
    }
}
