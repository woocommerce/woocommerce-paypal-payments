<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use Mockery;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\PayPalSubscriptions\SubscriptionStatus;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\FreeTrialSubscriptionHelper;

use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Button\Helper\Context
 */
class ContextTest extends TestCase
{
    /**
     * GIVEN the current admin screen is null (no screen context established yet)
     * WHEN is_site_editor() is asked whether the Site Editor is active
     * THEN it reports false
     */
    public function testIsSiteEditorFalseWhenNoCurrentScreen(): void
    {
        when('get_current_screen')->justReturn(null);

        $this->assertFalse(Context::is_site_editor());
    }

    /**
     * GIVEN an admin screen whose base identifies a different admin page
     * WHEN is_site_editor() is asked whether the Site Editor is active
     * THEN it reports false
     *
     * @dataProvider non_site_editor_screen_base_provider
     */
    public function testIsSiteEditorFalseWhenScreenBaseIsNotSiteEditor(string $base): void
    {
        $screen = new \WP_Screen();
        $screen->base = $base;

        when('get_current_screen')->justReturn($screen);

        $this->assertFalse(Context::is_site_editor());
    }

    public function non_site_editor_screen_base_provider(): array
    {
        return [
            'post edit screen' => ['post'],
            'widgets screen' => ['widgets'],
        ];
    }

    /**
     * GIVEN the current admin screen is the block-based Site Editor
     * WHEN is_site_editor() is asked whether the Site Editor is active
     * THEN it reports true
     */
    public function testIsSiteEditorTrueWhenScreenBaseIsSiteEditor(): void
    {
        $screen = new \WP_Screen();
        $screen->base = 'site-editor';

        when('get_current_screen')->justReturn($screen);

        $this->assertTrue(Context::is_site_editor());
    }

    private function create_context(bool $vaulted, bool $free_trial_cart, ?Order $order = null): Context
    {
        $session = Mockery::mock(SessionHandler::class);
        $session->allows('is_free_trial_vaulted')->andReturn($vaulted);
        $session->allows('order')->andReturn($order);

        $helper = Mockery::mock(FreeTrialSubscriptionHelper::class);
        $helper->allows('is_free_trial_cart')->andReturn($free_trial_cart);

        return new Context($session, Mockery::mock(SubscriptionStatus::class), $helper);
    }

    /**
     * GIVEN a free trial saved-account state and a cart that is or is no longer a free trial, without a PayPal order
     * WHEN the continuation is checked
     * THEN it is a free-trial and a PayPal continuation only with a saved account on a cart that is still a free trial
     *
     * @dataProvider free_trial_continuation_provider
     */
    public function testFreeTrialContinuationRequiresSavedAccountAndFreeTrialCart(bool $vaulted, bool $free_trial_cart, bool $expected): void
    {
        $context = $this->create_context($vaulted, $free_trial_cart);

        $this->assertSame($expected, $context->is_free_trial_continuation());
        $this->assertSame($expected, $context->is_paypal_continuation());
    }

    public function free_trial_continuation_provider(): array
    {
        return [
            'saved account on a cart that is still a free trial' => [true, true, true],
            'saved account but the cart is no longer a free trial' => [true, false, false],
            'no saved account on a free-trial cart' => [false, true, false],
        ];
    }

    // No test covers the `! function_exists( 'get_current_screen' )` branch: Brain Monkey's
    // Functions\when() works by dynamically declaring the function the first time it is
    // stubbed, so once any test in this process calls when('get_current_screen'), the
    // function stays defined for the rest of the PHPUnit run (tearDown() only resets the
    // stub's return behavior, not PHP's function table). That makes "the function does not
    // exist" unobservable from within a test that also needs the stub for its other
    // scenarios, and this class cannot force a fresh PHP process.
}
