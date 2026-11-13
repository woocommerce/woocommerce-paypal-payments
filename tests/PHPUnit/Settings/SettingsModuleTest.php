<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Settings;

use WooCommerce\PayPalCommerce\ModularTestCase;
use function Brain\Monkey\Functions\when;

class SettingsModuleTest extends ModularTestCase
{
	/**
	 * Regression test for #4103.
	 *
	 * apply_branded_only_limitations() resolves the store currency, which can
	 * trigger a third-party 'woocommerce_currency' filter (e.g. Aelia) that calls
	 * translation functions. Registering it on 'init' (priority 1) runs it before
	 * textdomains are loaded, producing "Translation loading was triggered too
	 * early" notices in WordPress 6.7+. The work must be deferred to 'wp_loaded'.
	 */
	public function testBrandedOnlyLimitationsAreDeferredToWpLoaded(): void
	{
		$container = $this->bootstrapModule();

		$actions = array();
		when('add_action')->alias(
			function ( string $hook, $callback = null, int $priority = 10, int $accepted_args = 1 ) use ( &$actions ): bool {
				$actions[] = $hook;
				return true;
			}
		);
		when('add_filter')->justReturn(true);

		( new SettingsModule() )->run( $container );

		self::assertContains( 'wp_loaded', $actions, 'Branded-only limitations should be registered on wp_loaded.' );
		self::assertNotContains( 'init', $actions, 'Branded-only limitations must not run on init (translations load too early).' );
	}
}
