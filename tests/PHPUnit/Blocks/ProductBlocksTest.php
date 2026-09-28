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
    public function setUp(): void
    {
        parent::setUp();

        // Faithful stand-in for WordPress core's has_block(): a namespaced block name is
        // matched literally, a bare name is assumed to be a core/ block, and a null post
        // content never contains a block.
        when('has_block')->alias(static function ($block_name, $content = null): bool {
            if (false === strpos($block_name, '/')) {
                $block_name = 'core/' . $block_name;
            }

            return null !== $content && false !== strpos((string) $content, '<!-- wp:' . $block_name . ' ');
        });
    }

    /**
     * GIVEN a settings status that reports the product Pay Later placement on or off
     * WHEN is_messaging_enabled() is asked whether the product Pay Later placement is on
     * THEN it mirrors the settings status answer for the 'product' location
     *
     * @dataProvider messaging_enabled_provider
     */
    public function testIsMessagingEnabledReflectsSettingsStatusForProductLocation(bool $location_enabled): void
    {
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
     * GIVEN a settings status that reports the product Smart Buttons placement on or off
     * WHEN is_buttons_enabled() is asked whether the product Smart Buttons placement is on
     * THEN it mirrors the settings status answer for the 'product' location
     *
     * @dataProvider buttons_enabled_provider
     */
    public function testIsButtonsEnabledReflectsSettingsStatusForProductLocation(bool $location_enabled): void
    {
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

    /**
     * GIVEN no block template content applies to the current page
     * WHEN template_renders_blocks() is asked whether the template renders the blocks
     * THEN it reports false, since there is nothing to inspect
     */
    public function testTemplateRendersBlocksIsFalseWhenContentIsNull(): void
    {
        $this->assertFalse(ProductBlocks::template_renders_blocks(null));
    }

    /**
     * GIVEN a block template that delegates to the classic PHP template via the Classic
     *       template block, even when it also contains an add-to-cart anchor
     * WHEN template_renders_blocks() is asked whether the template renders the blocks
     * THEN it reports false, since the classic PHP template is authoritative and must render
     *      the buttons/messaging itself
     */
    public function testTemplateRendersBlocksIsFalseWhenLegacyTemplateBlockIsPresent(): void
    {
        $content = '<!-- wp:woocommerce/legacy-template {"template":"single-product"} /-->'
            . '<!-- wp:woocommerce/add-to-cart-form /-->';

        $this->assertFalse(ProductBlocks::template_renders_blocks($content));
    }

    /**
     * GIVEN a block template whose content places a Smart Buttons/Pay Later block explicitly,
     *       or contains an add-to-cart anchor the blocks are auto-inserted after
     * WHEN template_renders_blocks() is asked whether the template renders the blocks
     * THEN it reports true, so the classic product render path stands down
     *
     * @dataProvider template_renders_blocks_provider
     */
    public function testTemplateRendersBlocksIsTrueWhenAnAnchorOrExplicitBlockIsPresent(string $content): void
    {
        $this->assertTrue(ProductBlocks::template_renders_blocks($content));
    }

    public function template_renders_blocks_provider(): array
    {
        return array(
            'add-to-cart-form anchor present'          => array('<!-- wp:woocommerce/add-to-cart-form /-->'),
            'add-to-cart-with-options anchor present'   => array(
                '<!-- wp:woocommerce/add-to-cart-with-options -->'
                . '<!-- wp:woocommerce/product-buttons /-->'
                . '<!-- /wp:woocommerce/add-to-cart-with-options -->'
            ),
            'smart buttons block explicitly placed'     => array(
                '<!-- wp:woocommerce-paypal-payments/product-smart-buttons /-->'
            ),
            'pay later messaging block explicitly placed' => array(
                '<!-- wp:woocommerce-paypal-payments/product-paylater-messages /-->'
            ),
        );
    }

    /**
     * GIVEN a block template whose content contains only unrelated blocks
     * WHEN template_renders_blocks() is asked whether the template renders the blocks
     * THEN it reports false, since neither an anchor nor an explicit block is present
     */
    public function testTemplateRendersBlocksIsFalseWhenOnlyUnrelatedBlocksArePresent(): void
    {
        $content = '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->';

        $this->assertFalse(ProductBlocks::template_renders_blocks($content));
    }
}
