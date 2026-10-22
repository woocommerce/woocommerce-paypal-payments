<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Blocks\ProductSmartButtonsRenderer
 */
class ProductSmartButtonsRendererTest extends TestCase
{
    /** @var SettingsStatus|Mockery\MockInterface */
    private $settings_status;

    public function setUp(): void
    {
        parent::setUp();

        when('wp_kses_data')->returnArg();
        when('get_block_wrapper_attributes')->justReturn('');
        when('apply_filters')->returnArg(2);
        when('do_action')->justReturn(null);
        // No viewed product unless a test names one.
        when('wc_get_product')->justReturn(false);

        $this->settings_status = Mockery::mock(SettingsStatus::class);
    }

    /**
     * @param bool|null $owns_current_page true/false when the 'sdk-v6.owns-current-page'
     *                                     service is registered and answers accordingly;
     *                                     null when the service is absent altogether.
     */
    private function create_container(?bool $owns_current_page): ContainerInterface
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with('wcgateway.settings.status')->andReturn($this->settings_status);
        $container->shouldReceive('has')->with('sdk-v6.owns-current-page')->andReturn(null !== $owns_current_page);

        if (null !== $owns_current_page) {
            $container->shouldReceive('get')
                ->with('sdk-v6.owns-current-page')
                ->andReturn(static fn (): bool => $owns_current_page);
        }

        return $container;
    }

    /**
     * GIVEN the product Smart Buttons placement is disabled for the merchant
     * WHEN the block is rendered on a product page
     * THEN an empty string is returned, so no button wrapper reaches the page
     */
    public function testRendersEmptyStringWhenButtonsAreDisabledForProduct(): void
    {
        when('is_product')->justReturn(true);

        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('product')
            ->andReturn(false);

        $renderer = new ProductSmartButtonsRenderer();
        $container = $this->create_container(false);

        $html = $renderer->render(array(), $container);

        $this->assertSame('', $html);
    }

    /**
     * GIVEN the product Smart Buttons placement is enabled for the merchant
     * WHEN the block is rendered outside of a single product page
     * THEN an empty string is returned, since the wrapper only belongs on product pages
     */
    public function testRendersEmptyStringWhenCurrentRequestIsNotAProductPage(): void
    {
        when('is_product')->justReturn(false);

        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('product')
            ->andReturn(true);

        $renderer = new ProductSmartButtonsRenderer();
        $container = $this->create_container(false);

        $html = $renderer->render(array(), $container);

        $this->assertSame('', $html);
    }

    /**
     * GIVEN the product Smart Buttons placement is enabled and the current page is a
     *       single product page not owned by the SDK v6 stack
     * WHEN the block is rendered
     * THEN the wrapper mounts into the v5 button id, matching what SmartButton::button_renderer()
     *      produces so the existing v5 front-end pipeline hydrates it
     */
    public function testRendersV5WrapperIdWhenV6DoesNotOwnTheCurrentPage(): void
    {
        when('is_product')->justReturn(true);

        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('product')
            ->andReturn(true);

        $renderer = new ProductSmartButtonsRenderer();
        $container = $this->create_container(false);

        $html = $renderer->render(array(), $container);

        $this->assertStringContainsString('class="ppc-button-wrapper"', $html);
        $this->assertStringContainsString('id="ppc-button-ppcp-gateway"', $html);
        $this->assertStringNotContainsString('id="ppc-button-ppcp-gateway-v6"', $html);
    }

    /**
     * GIVEN the product Smart Buttons placement is enabled and the SDK v6 stack owns the
     *       current page
     * WHEN the block is rendered
     * THEN the wrapper mounts into the v6 button id, so boot.js hydrates it instead of v5
     */
    public function testRendersV6WrapperIdWhenV6OwnsTheCurrentPage(): void
    {
        when('is_product')->justReturn(true);

        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('product')
            ->andReturn(true);

        $renderer = new ProductSmartButtonsRenderer();
        $container = $this->create_container(true);

        $html = $renderer->render(array(), $container);

        $this->assertStringContainsString('id="ppc-button-ppcp-gateway-v6"', $html);
    }

    /**
     * GIVEN the product Smart Buttons placement is enabled and the viewed product is free
     * WHEN the block is rendered
     * THEN no wrapper is rendered when nothing can be charged for shipping either, since
     *      PayPal rejects a zero-total order
     * AND the wrapper is rendered when shipping can still add a cost to the order
     *
     * @dataProvider free_product_shipping_provider
     */
    public function testFreeProductWrapperDependsOnWhetherShippingCanBeCharged(
        bool $needs_shipping,
        bool $shipping_enabled,
        bool $expect_wrapper
    ): void {
        when('is_product')->justReturn(true);
        $this->settings_status->shouldReceive('is_smart_button_enabled_for_location')
            ->with('product')
            ->andReturn(true);

        $product = Mockery::mock(\WC_Product::class);
        $product->shouldReceive('is_type')->andReturn(false);
        $product->shouldReceive('needs_shipping')->andReturn($needs_shipping);
        when('wc_get_product')->justReturn($product);
        when('wc_get_price_including_tax')->justReturn(0.0);
        when('wc_shipping_enabled')->justReturn($shipping_enabled);

        $html = (new ProductSmartButtonsRenderer())->render(array(), $this->create_container(false));

        if ($expect_wrapper) {
            $this->assertStringContainsString('class="ppc-button-wrapper"', $html);
        } else {
            $this->assertSame('', $html);
        }
    }

    public function free_product_shipping_provider(): array
    {
        return [
            'free product needing no shipping renders nothing' => [false, true, false],
            'free product with shipping disabled renders nothing' => [true, false, false],
            'free product with paid shipping possible still renders' => [true, true, true],
        ];
    }
}
