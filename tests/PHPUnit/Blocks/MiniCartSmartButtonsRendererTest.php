<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\FreeTrialSubscriptionHelper;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Blocks\MiniCartSmartButtonsRenderer
 */
class MiniCartSmartButtonsRendererTest extends TestCase
{
    /** @var SettingsStatus|Mockery\MockInterface */
    private $settings_status;

    public function setUp(): void
    {
        parent::setUp();

        when('wp_kses_data')->returnArg();
        when('esc_attr')->returnArg();
        when('get_block_wrapper_attributes')->justReturn('');
        when('apply_filters')->returnArg(2);
        when('do_action')->justReturn(null);

        $this->settings_status = Mockery::mock(SettingsStatus::class);
    }

    private function create_container(bool $is_free_trial_cart = false): ContainerInterface
    {
        $free_trial_helper = Mockery::mock(FreeTrialSubscriptionHelper::class);
        $free_trial_helper->shouldReceive('is_free_trial_cart')->andReturn($is_free_trial_cart);

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with('wcgateway.settings.status')->andReturn($this->settings_status);
        $container->shouldReceive('get')
            ->with('wc-subscriptions.free-trial-subscription-helper')
            ->andReturn($free_trial_helper);

        return $container;
    }

    /**
     * GIVEN the mini-cart Smart Buttons placement is disabled for the merchant
     * WHEN the block is rendered
     * THEN an empty string is returned, so no button wrapper reaches the mini-cart drawer
     */
    public function testRendersEmptyStringWhenButtonsAreDisabledForMiniCart(): void
    {
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('mini-cart')
            ->andReturn(false);

        $renderer = new MiniCartSmartButtonsRenderer();
        $container = $this->create_container();

        $html = $renderer->render(array(), $container);

        $this->assertSame('', $html);
    }

    /**
     * GIVEN the mini-cart Smart Buttons placement is enabled for the merchant
     * WHEN the block is rendered
     * THEN the markup contains the block wrapper, the mini-cart buttons paragraph, and the
     *      `#ppc-button-minicart-v6` span that boot.js hydrates
     */
    public function testRendersButtonWrapperWhenButtonsAreEnabledForMiniCart(): void
    {
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('mini-cart')
            ->andReturn(true);

        $renderer = new MiniCartSmartButtonsRenderer();
        $container = $this->create_container();

        $html = $renderer->render(array(), $container);

        $this->assertStringContainsString('<div >', $html);
        $this->assertStringContainsString('class="woocommerce-mini-cart__buttons buttons"', $html);
        $this->assertStringContainsString('<span id="ppc-button-minicart-v6"></span>', $html);
    }

    /**
     * GIVEN the mini-cart Smart Buttons placement is enabled for the merchant
     * AND the cart contains a free trial subscription
     * WHEN the block is rendered
     * THEN an empty string is returned, because a free trial can only be vaulted at the checkout
     */
    public function testRendersEmptyStringWhenCartIsFreeTrial(): void
    {
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('mini-cart')
            ->andReturn(true);

        $renderer = new MiniCartSmartButtonsRenderer();
        $container = $this->create_container(true);

        $html = $renderer->render(array(), $container);

        $this->assertSame('', $html);
    }
}
