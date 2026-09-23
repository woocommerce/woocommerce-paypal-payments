<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Blocks;

use Mockery;
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

    public function setUp(): void
    {
        parent::setUp();

        when('wp_kses_data')->returnArg();
        when('get_block_wrapper_attributes')->justReturn('');
        when('apply_filters')->returnArg(2);
        when('getenv')->justReturn('1');

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
