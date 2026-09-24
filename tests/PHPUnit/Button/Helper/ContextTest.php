<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use WooCommerce\PayPalCommerce\TestCase;

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

    // No test covers the `! function_exists( 'get_current_screen' )` branch: Brain Monkey's
    // Functions\when() works by dynamically declaring the function the first time it is
    // stubbed, so once any test in this process calls when('get_current_screen'), the
    // function stays defined for the rest of the PHPUnit run (tearDown() only resets the
    // stub's return behavior, not PHP's function table). That makes "the function does not
    // exist" unobservable from within a test that also needs the stub for its other
    // scenarios, and this class cannot force a fresh PHP process.
}
