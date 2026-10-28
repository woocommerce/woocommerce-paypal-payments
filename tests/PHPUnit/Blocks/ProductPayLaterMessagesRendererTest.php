<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Mockery;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PartnerAttribution;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Blocks\ProductPayLaterMessagesRenderer
 */
class ProductPayLaterMessagesRendererTest extends TestCase
{
    /** @var SettingsStatus|Mockery\MockInterface */
    private $settings_status;

    /** @var PartnerAttribution|Mockery\MockInterface */
    private $partner_attribution;

    /** Value a merchant filter returns for product payment support; null when no filter is attached. */
    private ?bool $payment_support_filter_result = null;

    public function setUp(): void
    {
        parent::setUp();

        when('wp_kses_data')->returnArg();
        when('get_block_wrapper_attributes')->justReturn('');
        $this->payment_support_filter_result = null;
        when('apply_filters')->alias(
            fn (string $hook, $value) => 'woocommerce_paypal_payments_product_supports_payment_request_button' === $hook
                && null !== $this->payment_support_filter_result
                    ? $this->payment_support_filter_result
                    : $value
        );
        // No viewed product unless a test names one.
        when('wc_get_product')->justReturn(false);

        $this->settings_status = Mockery::mock(SettingsStatus::class);
        $this->partner_attribution = Mockery::mock(PartnerAttribution::class);
        $this->partner_attribution->shouldReceive('get_bn_code')->andReturn('Woo_PPCP');
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
        $container->shouldReceive('get')->with('api.helper.partner-attribution')->andReturn($this->partner_attribution);
        $container->shouldReceive('has')->with('sdk-v6.owns-current-page')->andReturn(null !== $owns_current_page);

        if (null !== $owns_current_page) {
            $container->shouldReceive('get')
                ->with('sdk-v6.owns-current-page')
                ->andReturn(static fn (): bool => $owns_current_page);
        }

        return $container;
    }

    /**
     * @param bool[] $variation_stock In-stock flag of each available variation (variable products only).
     */
    private function create_product(string $type, bool $in_stock, array $variation_stock = []): object
    {
        $class = 'variable' === $type ? WC_Product_Variable::class : WC_Product::class;

        $product = Mockery::mock($class);
        $product->shouldReceive('is_type')
            ->andReturnUsing(static fn ($types): bool => in_array($type, (array) $types, true));
        $product->shouldReceive('is_in_stock')->andReturn($in_stock);

        if ('variable' === $type) {
            $variations = array_map(
                static function (bool $variation_in_stock): WC_Product_Variation {
                    $variation = Mockery::mock(WC_Product_Variation::class);
                    $variation->shouldReceive('is_in_stock')->andReturn($variation_in_stock);

                    return $variation;
                },
                $variation_stock
            );
            $product->shouldReceive('get_available_variations')->andReturn($variations);
        }

        return $product;
    }

    /**
     * GIVEN the product Pay Later messaging placement is enabled and a product is being viewed
     * WHEN the block is rendered
     * THEN the message wrapper is rendered only if the product can be bought
     *
     * @dataProvider product_buyability_provider
     */
    public function testWrapperDependsOnWhetherProductCanBeBought(
        string $type,
        bool $in_stock,
        array $variation_stock,
        bool $expect_wrapper
    ): void {
        $this->settings_status->shouldReceive('is_pay_later_messaging_enabled_for_location')
            ->with('product')
            ->andReturn(true);
        when('wc_get_product')->justReturn($this->create_product($type, $in_stock, $variation_stock));

        $html = (new ProductPayLaterMessagesRenderer(array()))->render(array(), $this->create_container(false));

        $this->assertSame($expect_wrapper, false !== strpos($html, 'class="ppcp-messages"'));
    }

    public function product_buyability_provider(): array
    {
        return [
            'in-stock simple product renders' => ['simple', true, [], true],
            'out-of-stock simple product renders nothing' => ['simple', false, [], false],
            'external product renders nothing' => ['external', true, [], false],
            'grouped product renders nothing' => ['grouped', true, [], false],
            'variable product with an in-stock variation renders' => ['variable', false, [false, true], true],
            'variable product with only out-of-stock variations renders nothing' => ['variable', true, [false, false], false],
            'variable product without variations renders nothing' => ['variable', true, [], false],
        ];
    }

    /**
     * GIVEN a merchant filter decides if the viewed in-stock simple product supports PayPal buttons
     * WHEN the block is rendered
     * THEN the message wrapper is rendered only if the filter allows it
     *
     * @dataProvider payment_support_filter_provider
     */
    public function testWrapperFollowsPaymentSupportFilter(bool $filter_result): void
    {
        $this->payment_support_filter_result = $filter_result;
        $this->settings_status->shouldReceive('is_pay_later_messaging_enabled_for_location')
            ->with('product')
            ->andReturn(true);
        when('wc_get_product')->justReturn($this->create_product('simple', true));

        $html = (new ProductPayLaterMessagesRenderer(array()))->render(array(), $this->create_container(false));

        $this->assertSame($filter_result, false !== strpos($html, 'class="ppcp-messages"'));
    }

    public function payment_support_filter_provider(): array
    {
        return [
            'filter denies support' => [false],
            'filter allows support' => [true],
        ];
    }

    /**
     * GIVEN the product Pay Later messaging placement is disabled for the merchant
     * WHEN the block is rendered
     * THEN an empty string is returned, so no placeholder markup reaches the page
     */
    public function testRendersEmptyStringWhenMessagingIsDisabledForProduct(): void
    {
        $this->settings_status->shouldReceive('is_pay_later_messaging_enabled_for_location')
            ->with('product')
            ->andReturn(false);

        $renderer = new ProductPayLaterMessagesRenderer(array());
        $container = $this->create_container(false);

        $html = $renderer->render(array('ppcpId' => 'ppcp-product-paylater-messages'), $container);

        $this->assertSame('', $html);
    }

    /**
     * GIVEN the product Pay Later messaging placement is enabled, configured with a flex layout
     * WHEN the block is rendered on a page v6 does not own
     * THEN it emits the placeholder with the 'product' placement and flex style attributes
     * AND the text-only attributes (logo type/position, text color/size) are absent
     */
    public function testRendersFlexLayoutAttributesWhenV6DoesNotOwnTheCurrentPage(): void
    {
        $this->settings_status->shouldReceive('is_pay_later_messaging_enabled_for_location')
            ->with('product')
            ->andReturn(true);

        $renderer = new ProductPayLaterMessagesRenderer(
            array(
                'layout'     => 'flex',
                'flex_color' => 'blue',
                'flex_ratio' => '8x1',
            )
        );
        $container = $this->create_container(false);

        $html = $renderer->render(array('ppcpId' => 'ppcp-product-paylater-messages'), $container);

        $this->assertStringContainsString('class="ppcp-messages"', $html);
        $this->assertStringContainsString('data-pp-placement="product"', $html);
        $this->assertStringContainsString('data-pp-style-layout="flex"', $html);
        $this->assertStringContainsString('data-pp-style-color="blue"', $html);
        $this->assertStringContainsString('data-pp-style-ratio="8x1"', $html);
        $this->assertStringNotContainsString('data-pp-style-logo-type=', $html);
        $this->assertStringNotContainsString('data-pp-style-logo-position=', $html);
    }

    /**
     * GIVEN the product Pay Later messaging placement is enabled, configured with a text layout
     * WHEN the block is rendered on a page v6 does not own
     * THEN it emits the text style attributes (logo type/position, text color/size)
     * AND the flex-only attributes (color, ratio) are absent
     */
    public function testRendersTextLayoutAttributesWhenV6DoesNotOwnTheCurrentPage(): void
    {
        $this->settings_status->shouldReceive('is_pay_later_messaging_enabled_for_location')
            ->with('product')
            ->andReturn(true);

        $renderer = new ProductPayLaterMessagesRenderer(
            array(
                'layout'    => 'text',
                'logo'      => 'primary',
                'position'  => 'left',
                'color'     => 'black',
                'text_size' => '12',
            )
        );
        $container = $this->create_container(null);

        $html = $renderer->render(array('ppcpId' => 'ppcp-product-paylater-messages'), $container);

        $this->assertStringContainsString('data-pp-style-layout="text"', $html);
        $this->assertStringContainsString('data-pp-style-logo-type="primary"', $html);
        $this->assertStringContainsString('data-pp-style-logo-position="left"', $html);
        $this->assertStringContainsString('data-pp-style-text-color="black"', $html);
        $this->assertStringContainsString('data-pp-style-text-size="12"', $html);
        $this->assertStringNotContainsString('data-pp-style-color=', $html);
        $this->assertStringNotContainsString('data-pp-style-ratio=', $html);
    }

    /**
     * GIVEN v6 owns the current page, and the renderer was configured with a flex layout
     * WHEN the product Pay Later placement is rendered
     * THEN it is coerced to render as a text message, since v6's messaging component only
     *      styles text messages
     * AND the flex-only attributes (color, ratio) are absent
     */
    public function testCoercesToTextLayoutWhenV6OwnsTheCurrentPageEvenWithFlexLayoutConfig(): void
    {
        $this->settings_status->shouldReceive('is_pay_later_messaging_enabled_for_location')
            ->with('product')
            ->andReturn(true);

        $renderer = new ProductPayLaterMessagesRenderer(
            array(
                'layout'     => 'flex',
                'flex_color' => 'blue',
                'flex_ratio' => '8x1',
            )
        );
        $container = $this->create_container(true);

        $html = $renderer->render(array('ppcpId' => 'ppcp-product-paylater-messages'), $container);

        $this->assertStringContainsString('data-pp-style-layout="text"', $html);
        $this->assertStringNotContainsString('data-pp-style-color=', $html);
        $this->assertStringNotContainsString('data-pp-style-ratio=', $html);
    }

    /**
     * GIVEN the product Pay Later messaging placement is enabled
     * WHEN the block is rendered, regardless of the configured layout
     * THEN the placement is always reported as 'product'
     */
    public function testPlacementIsAlwaysProduct(): void
    {
        $this->settings_status->shouldReceive('is_pay_later_messaging_enabled_for_location')
            ->with('product')
            ->andReturn(true);

        $renderer = new ProductPayLaterMessagesRenderer(array('layout' => 'text'));
        $container = $this->create_container(false);

        $html = $renderer->render(array('ppcpId' => 'ppcp-product-paylater-messages'), $container);

        $this->assertStringContainsString('data-pp-placement="product"', $html);
    }
}
