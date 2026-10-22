<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Brain\Monkey\Actions;
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

        if (!defined('ABSPATH')) {
            define('ABSPATH', '/var/www/html/');
        }
        if (!defined('WPINC')) {
            define('WPINC', 'wp-includes');
        }

        $this->set_static('neutralized_from', null);
        $this->set_static('classic_render_restored', false);

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
     * GIVEN a block template containing only the single-product price block
     * WHEN template_renders_blocks() is asked whether the template renders the blocks
     * THEN it reports true, since the messaging block is auto-inserted after the price
     */
    public function testTemplateRendersBlocksIsTrueWhenOnlyThePriceAnchorIsPresent(): void
    {
        $content = '<!-- wp:woocommerce/product-price {"isDescendentOfSingleProductTemplate":true} /-->';

        $this->assertTrue(ProductBlocks::template_renders_blocks($content));
    }

    /**
     * GIVEN no filter overrides the messaging placement
     * WHEN messaging_placement() is called
     * THEN the messaging block is placed after the product price block
     */
    public function testMessagingPlacementDefaultsToAfterTheProductPrice(): void
    {
        $this->assertSame(
            array('anchor' => array('woocommerce/product-price'), 'position' => 'after'),
            ProductBlocks::messaging_placement()
        );
    }

    /**
     * GIVEN a filter callback that overrides the messaging placement
     * WHEN messaging_placement() is called
     * THEN the placement is normalized to a list of anchors, or falls back to the default
     *      when the override is invalid
     *
     * @dataProvider messaging_placement_provider
     * @param mixed $filtered
     */
    public function testMessagingPlacementHonoursValidOverridesAndFallsBackOtherwise($filtered, array $expected): void
    {
        when('apply_filters')->alias(
            static fn ($hook, $value) => 'woocommerce_paypal_payments_product_messages_block_placement' === $hook ? $filtered : $value
        );

        $this->assertSame($expected, ProductBlocks::messaging_placement());
    }

    public function messaging_placement_provider(): array
    {
        $default = array('anchor' => array('woocommerce/product-price'), 'position' => 'after');

        return array(
            'string anchor'                    => array(
                array('anchor' => 'woocommerce/post-title', 'position' => 'before'),
                array('anchor' => array('woocommerce/post-title'), 'position' => 'before'),
            ),
            'array anchor'                     => array(
                array('anchor' => array('a/one', 'a/two'), 'position' => 'last_child'),
                array('anchor' => array('a/one', 'a/two'), 'position' => 'last_child'),
            ),
            'array anchor drops invalid items' => array(
                array('anchor' => array('a/one', '', 5), 'position' => 'after'),
                array('anchor' => array('a/one'), 'position' => 'after'),
            ),
            'non-array filter result'          => array('nope', $default),
            'invalid position'                 => array(array('anchor' => 'a/one', 'position' => 'middle'), $default),
            'missing position'                 => array(array('anchor' => 'a/one'), $default),
            'empty string anchor'              => array(array('anchor' => '', 'position' => 'after'), $default),
            'empty anchor array'               => array(array('anchor' => array(), 'position' => 'after'), $default),
            'anchor array of non-strings'      => array(array('anchor' => array(1, null), 'position' => 'after'), $default),
            'anchor array of empty strings'    => array(array('anchor' => array('', ''), 'position' => 'after'), $default),
            'non-string non-array anchor'      => array(array('anchor' => 42, 'position' => 'after'), $default),
        );
    }

    /**
     * GIVEN a parsed anchor block
     * WHEN is_single_product_price_anchor() is asked whether messaging may be inserted at it
     * THEN a price block qualifies only when it belongs to the single product and not a query loop
     * AND any other block qualifies, and a non-array never does
     *
     * @dataProvider single_product_price_anchor_provider
     * @param mixed $anchor
     */
    public function testIsSingleProductPriceAnchor($anchor, bool $expected): void
    {
        $this->assertSame($expected, ProductBlocks::is_single_product_price_anchor($anchor));
    }

    public function single_product_price_anchor_provider(): array
    {
        return array(
            'price of the single product'   => array(
                array('blockName' => 'woocommerce/product-price', 'attrs' => array('isDescendentOfSingleProductTemplate' => true)),
                true,
            ),
            'price inside a query loop'     => array(
                array('blockName' => 'woocommerce/product-price', 'attrs' => array('isDescendentOfQueryLoop' => true)),
                false,
            ),
            'price flagged for both'        => array(
                array('blockName' => 'woocommerce/product-price', 'attrs' => array('isDescendentOfSingleProductTemplate' => true, 'isDescendentOfQueryLoop' => true)),
                false,
            ),
            'price without attributes'      => array(array('blockName' => 'woocommerce/product-price'), false),
            'other anchor block'            => array(array('blockName' => 'woocommerce/add-to-cart-form'), true),
            'non-array anchor'              => array('woocommerce/product-price', false),
            'null anchor'                   => array(null, false),
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

    /**
     * GIVEN the template WordPress is about to render is core's block-template canvas
     *       (in any path spelling)
     * WHEN builder_overrode_template() is asked whether a page builder replaced the template
     * THEN it reports false, since the block template and its blocks will render
     *
     * @dataProvider canvas_template_provider
     */
    public function testBuilderOverrodeTemplateIsFalseForTheCoreCanvas(string $spelling): void
    {
        $canvas   = $this->canvas_template();
        $template = array(
            'core'           => $canvas,
            'backslashes'    => str_replace('/', '\\', $canvas),
            'double slashes' => '/' . str_replace('/', '//', substr($canvas, 1)),
        )[$spelling];

        $this->assertFalse(ProductBlocks::builder_overrode_template($template));
    }

    public function canvas_template_provider(): array
    {
        return array(
            'canvas path as core builds it'   => array('core'),
            'canvas path with backslashes'    => array('backslashes'),
            'canvas path with double slashes' => array('double slashes'),
        );
    }

    /**
     * GIVEN a page builder supplies its own template file
     * WHEN builder_overrode_template() is asked whether a page builder replaced the template
     * THEN it reports true
     */
    public function testBuilderOverrodeTemplateIsTrueForAnotherTemplate(): void
    {
        $template = '/var/www/wp-content/plugins/elementor/modules/page-templates/templates/canvas.php';

        $this->assertTrue(ProductBlocks::builder_overrode_template($template));
    }

    /**
     * GIVEN a template_include value that is not a usable path
     * WHEN builder_overrode_template() is asked whether a page builder replaced the template
     * THEN it reports false, since nothing can be concluded from it
     *
     * @dataProvider unusable_template_provider
     * @param mixed $template
     */
    public function testBuilderOverrodeTemplateIsFalseForUnusableValues($template): void
    {
        $this->assertFalse(ProductBlocks::builder_overrode_template($template));
    }

    public function unusable_template_provider(): array
    {
        return array(
            'null'         => array(null),
            'array'        => array(array('canvas.php')),
            'empty string' => array(''),
        );
    }

    /**
     * GIVEN the classic product render was redirected away from a hook and a page builder
     *       replaced the block template
     * WHEN the classic render is restored twice
     * THEN the callbacks are bridged back onto the original hook only once
     */
    public function testRestoreClassicRenderBridgesOntoTheOriginalHookOnce(): void
    {
        $this->set_static('neutralized_from', 'woocommerce_after_add_to_cart_form');
        Actions\expectAdded('woocommerce_after_add_to_cart_form')
            ->once()
            ->with(Mockery::type('Closure'), 30);

        ProductBlocks::restore_classic_render_for_builders('/var/www/wp-content/plugins/elementor/canvas.php');
        ProductBlocks::restore_classic_render_for_builders('/var/www/wp-content/plugins/elementor/canvas.php');

        $this->addToAssertionCount(1);
    }

    /**
     * GIVEN the classic product render was redirected away from a hook
     * WHEN the template about to render is core's block-template canvas
     * THEN nothing is bridged back, since the blocks render the page
     */
    public function testRestoreClassicRenderDoesNothingWhenCoreCanvasRenders(): void
    {
        $this->set_static('neutralized_from', 'woocommerce_after_add_to_cart_form');
        Actions\expectAdded('woocommerce_after_add_to_cart_form')->never();

        ProductBlocks::restore_classic_render_for_builders($this->canvas_template());

        $this->addToAssertionCount(1);
    }

    /**
     * GIVEN the classic product render was never redirected on this request
     * WHEN a page builder replaces the template
     * THEN nothing is bridged back, since the classic path still renders
     */
    public function testRestoreClassicRenderDoesNothingWhenRenderWasNotRedirected(): void
    {
        $this->set_static('neutralized_from', null);
        Actions\expectAdded('woocommerce_after_add_to_cart_form')->never();

        ProductBlocks::restore_classic_render_for_builders('/var/www/wp-content/plugins/elementor/canvas.php');

        $this->addToAssertionCount(1);
    }

    public function tearDown(): void
    {
        $this->set_static('neutralized_from', null);
        $this->set_static('classic_render_restored', false);

        parent::tearDown();
    }

    private function canvas_template(): string
    {
        return ABSPATH . WPINC . '/template-canvas.php';
    }

    /**
     * @param mixed $value
     */
    private function set_static(string $property, $value): void
    {
        $reflection = new \ReflectionProperty(ProductBlocks::class, $property);
        $reflection->setAccessible(true);
        $reflection->setValue(null, $value);
    }
}
