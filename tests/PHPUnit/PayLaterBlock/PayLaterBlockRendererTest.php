<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\PayLaterBlock;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PartnerAttribution;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
use function Brain\Monkey\Functions\when;

/**
 * PCP-6829: the block's own layout attribute is coerced to 'text' only when v6
 * actually owns the current page ('sdk-v6.owns-current-page' resolves true) -
 * because v6's messaging web component only styles text messages. Where v6
 * stands down (module absent, or present but not owning this particular page)
 * the v5 smart button renders the page instead and can still draw a flex
 * banner, so the block must keep rendering exactly as v5 always has.
 */
class PayLaterBlockRendererTest extends TestCase
{
    /** @var SettingsStatus|Mockery\MockInterface */
    private $settings_status;

    /** @var PartnerAttribution|Mockery\MockInterface */
    private $partner_attribution;

    public function setUp(): void
    {
        parent::setUp();

        when('get_block_wrapper_attributes')->justReturn('class="wp-block-woocommerce-paypal-payments-paylater-block"');
        when('wp_kses_data')->returnArg();

        $this->settings_status = Mockery::mock(SettingsStatus::class);
        $this->settings_status->shouldReceive('is_pay_later_messaging_enabled_for_location')
            ->with('custom_placement')
            ->andReturn(true);

        $this->partner_attribution = Mockery::mock(PartnerAttribution::class);
        $this->partner_attribution->shouldReceive('get_bn_code')->andReturn('Woo_PPCP');
    }

    /**
     * @param bool|null $owns_current_page true/false when the 'sdk-v6.owns-current-page'
     *                                     service is registered and answers accordingly;
     *                                     null when the service is absent altogether
     *                                     (the v6 module is not loaded on this site).
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
     * GIVEN v6 owns the current page (the 'sdk-v6.owns-current-page' callable
     *       resolves true), and a Pay Later block was saved with a flex layout and
     *       its flex/text style attributes
     * WHEN the block is rendered
     * THEN it is coerced to render as a text message, carrying the text style
     *      attributes (logo type/position, text color/size)
     * AND none of the flex-only attributes (color, ratio) are emitted, since v6's
     *     messaging component only styles text messages
     */
    public function testCoercesToTextLayoutWhenV6OwnsTheCurrentPageEvenWithFlexLayoutAttribute(): void
    {
        $renderer  = new PayLaterBlockRenderer();
        $container = $this->create_container(true);

        $html = $renderer->render(
            array(
                'id'        => 'ppcp-1',
                'layout'    => 'flex',
                'logo'      => 'primary',
                'position'  => 'left',
                'color'     => 'black',
                'size'      => '12',
                'flexColor' => 'blue',
                'flexRatio' => '8x1',
            ),
            $container
        );

        $this->assertStringContainsString('data-pp-style-layout="text"', $html);
        $this->assertStringContainsString('data-pp-style-logo-type="primary"', $html);
        $this->assertStringContainsString('data-pp-style-logo-position="left"', $html);
        $this->assertStringContainsString('data-pp-style-text-color="black"', $html);
        $this->assertStringContainsString('data-pp-style-text-size="12"', $html);
        $this->assertStringNotContainsString('data-pp-style-color=', $html);
        $this->assertStringNotContainsString('data-pp-style-ratio=', $html);
    }

    /**
     * GIVEN the v6 SDK module is not loaded at all ('sdk-v6.owns-current-page' is not
     *       registered in the container), and a Pay Later block was saved with a flex
     *       layout
     * WHEN the block is rendered
     * THEN it keeps rendering as a flex message with its flex style attributes -
     *      the v5 behaviour, unaffected by the v6 coercion
     */
    public function testKeepsFlexLayoutWhenSdkV6ModuleIsNotLoaded(): void
    {
        $renderer  = new PayLaterBlockRenderer();
        $container = $this->create_container(null);

        $html = $renderer->render(
            array(
                'id'        => 'ppcp-1',
                'layout'    => 'flex',
                'flexColor' => 'blue',
                'flexRatio' => '8x1',
            ),
            $container
        );

        $this->assertStringContainsString('data-pp-style-layout="flex"', $html);
        $this->assertStringContainsString('data-pp-style-color="blue"', $html);
        $this->assertStringContainsString('data-pp-style-ratio="8x1"', $html);
        $this->assertStringNotContainsString('data-pp-style-logo-type=', $html);
    }

    /**
     * GIVEN the v6 module is loaded, but v6 does not own the current page (e.g. the
     *       merchant disabled all v6 button locations for it), so the real v5
     *       SmartButton renders the page instead
     * WHEN a Pay Later block saved with a flex layout is rendered
     * THEN it keeps rendering as a flex message with its flex style attributes - v5
     *      can still draw the configured banner on a page it owns
     *
     * This is the regression the fix in PayLaterBlockModule::v6_owns_current_page()
     * addresses: gating on "is the v6 module loaded" alone (rather than "does v6 own
     * this page") downgraded a merchant's flex banner to text on pages v5 renders.
     */
    public function testKeepsFlexLayoutWhenV6ModuleIsLoadedButDoesNotOwnTheCurrentPage(): void
    {
        $renderer  = new PayLaterBlockRenderer();
        $container = $this->create_container(false);

        $html = $renderer->render(
            array(
                'id'        => 'ppcp-1',
                'layout'    => 'flex',
                'flexColor' => 'blue',
                'flexRatio' => '8x1',
            ),
            $container
        );

        $this->assertStringContainsString('data-pp-style-layout="flex"', $html);
        $this->assertStringContainsString('data-pp-style-color="blue"', $html);
        $this->assertStringContainsString('data-pp-style-ratio="8x1"', $html);
        $this->assertStringNotContainsString('data-pp-style-logo-type=', $html);
    }
}
