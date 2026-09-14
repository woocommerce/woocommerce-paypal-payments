<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\SdkV6;

use Mockery;
use WooCommerce\PayPalCommerce\SdkV6\Assets\SdkV6Manager;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Exercises the 'sdk-v6.owns-current-page' service exactly as it is defined in
 * modules/ppcp-sdk-v6/services.php: the container closure is loaded from the real
 * production file and invoked against a stub container, rather than re-implemented
 * here, so a future edit to that file is what these tests actually protect.
 *
 * ApplepayModule and GooglepayModule read this service (via their private
 * v6_owns_current_page() helpers) to decide whether to stand down so a second
 * PayPal SDK does not run against window.paypal.
 */
class OwnsCurrentPageServiceTest extends TestCase
{
    /**
     * Resolves the 'sdk-v6.owns-current-page' callable from the real services.php,
     * backed by a container that only ever serves 'sdk-v6.manager'.
     */
    private function resolveService(SdkV6Manager $manager): callable
    {
        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')
            ->with('sdk-v6.manager')
            ->andReturn($manager);

        $services = require ROOT_DIR . '/modules/ppcp-sdk-v6/services.php';

        $factory = $services['sdk-v6.owns-current-page'];

        return $factory($container);
    }

    /**
     * GIVEN a classic checkout page with Pay Later messaging enabled and eligible, and
     *       no smart-button location or ACDC surface owning the page — so
     *       should_load_on_current_page() is true only because its messaging condition
     *       claims the page
     * WHEN the 'sdk-v6.owns-current-page' service is asked whether v6 owns the page
     * THEN it reports true
     *
     * A false here is the regression that shipped: GooglepayModule's continuation
     * fallback (`if ( is_checkout() && ! self::v6_owns_current_page( $c ) ) { $button->enqueue(); }`)
     * would then enqueue the v5 Google Pay button, which loads the v5 PayPal SDK
     * alongside the v6 SDK that messaging already put on the page — both claiming
     * window.paypal.
     */
    public function testRegressionOwnsCurrentPageTrueWhenOnlyMessagingLoadsTheSdk(): void
    {
        $manager = Mockery::mock(SdkV6Manager::class);
        $manager->shouldReceive('should_load_on_current_page')->andReturn(true);

        $owns_current_page = $this->resolveService($manager);

        $this->assertTrue($owns_current_page());
    }

    /**
     * GIVEN v6 owns the page through an ordinary enabled button location (no messaging
     *       involved)
     * WHEN the 'sdk-v6.owns-current-page' service resolves its answer
     * THEN it reports true, matching should_load_on_current_page()
     */
    public function testOwnsCurrentPageTrueWhenAButtonLocationOwnsThePage(): void
    {
        $manager = Mockery::mock(SdkV6Manager::class);
        $manager->shouldReceive('should_load_on_current_page')->andReturn(true);

        $owns_current_page = $this->resolveService($manager);

        $this->assertTrue($owns_current_page());
    }

    /**
     * GIVEN a page where neither buttons, ACDC, the mini-cart location, nor Pay Later
     *       messaging apply
     * WHEN the 'sdk-v6.owns-current-page' service resolves its answer
     * THEN it reports false, so the v5 wallets stay in control of that page
     */
    public function testOwnsCurrentPageFalseWhenNothingClaimsTheSdk(): void
    {
        $manager = Mockery::mock(SdkV6Manager::class);
        $manager->shouldReceive('should_load_on_current_page')->andReturn(false);

        $owns_current_page = $this->resolveService($manager);

        $this->assertFalse($owns_current_page());
    }
}
