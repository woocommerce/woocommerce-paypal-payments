<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Compat\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WooCommerce\PayPalCommerce\Compat\CompatModule;

/**
 * Testable subclass exposing protected methods for unit testing.
 */
class TestableCompatModule extends CompatModule {
	public function call_disable_legacy_paypal_standard_on_connect(): void {
		$this->disable_legacy_paypal_standard_on_connect();
	}

	public function call_maybe_show_wps_subscriptions_notice(): string {
		ob_start();
		$this->maybe_show_wps_subscriptions_notice();
		return ob_get_clean() ?: '';
	}
}

/**
 * @covers \WooCommerce\PayPalCommerce\Compat\CompatModule::disable_legacy_paypal_standard_on_connect
 * @covers \WooCommerce\PayPalCommerce\Compat\CompatModule::maybe_show_wps_subscriptions_notice
 */
class DisableLegacyPaypalStandardTest extends TestCase {

	/** @var TestableCompatModule */
	private TestableCompatModule $sut;

	protected function setUp(): void {
		parent::setUp();
		$this->sut = new TestableCompatModule();

		// Reset option state between tests.
		delete_option( 'woocommerce_paypal_settings' );
		delete_option( 'woocommerce_restore_paypal_standard_settings' );
		delete_transient( 'ppcp_wps_standard_subs_notice' );
	}

	// ── disable_legacy_paypal_standard_on_connect ──────────────────────────

	/**
	 * @test
	 *
	 * GIVEN WooCommerce Subscriptions is not installed
	 * WHEN  the merchant connects PPCP
	 * THEN  WPS is disabled unconditionally (both enabled and _should_load set to 'no')
	 */
	public function test_disables_wps_when_wcs_not_installed(): void {
		// function_exists('wcs_get_subscriptions') returns false in this env.
		update_option( 'woocommerce_paypal_settings', array( 'enabled' => 'yes', '_should_load' => 'yes' ) );

		$this->sut->call_disable_legacy_paypal_standard_on_connect();

		$settings = get_option( 'woocommerce_paypal_settings' );
		$this->assertSame( 'no', $settings['enabled'] );
		$this->assertSame( 'no', $settings['_should_load'] );
		$this->assertFalse( get_transient( 'ppcp_wps_standard_subs_notice' ) );
	}

	/**
	 * @test
	 *
	 * GIVEN WooCommerce Subscriptions is active with zero active PayPal Standard subscriptions
	 * WHEN  the merchant connects PPCP
	 * THEN  WPS is disabled (WCS guard does not block)
	 */
	public function test_disables_wps_when_wcs_has_no_active_paypal_subs(): void {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			$this->markTestSkipped( 'WooCommerce Subscriptions not available.' );
		}

		update_option( 'woocommerce_paypal_settings', array( 'enabled' => 'yes', '_should_load' => 'yes' ) );

		// wcs_get_subscriptions returns empty for both gateway IDs.
		$this->sut->call_disable_legacy_paypal_standard_on_connect();

		$settings = get_option( 'woocommerce_paypal_settings' );
		$this->assertSame( 'no', $settings['enabled'] );
		$this->assertSame( 'no', $settings['_should_load'] );
	}

	/**
	 * @test
	 *
	 * GIVEN WCS is active with at least one active native PayPal Standard subscription
	 * WHEN  the merchant connects PPCP
	 * THEN  WPS is NOT disabled and a transient is stored for the notice
	 *
	 * @dataProvider active_native_paypal_sub_counts
	 */
	public function test_stores_transient_and_skips_disable_when_native_paypal_subs_exist( int $count ): void {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			$this->markTestSkipped( 'WooCommerce Subscriptions not available.' );
		}

		// Seed WCS with active native PayPal subscriptions.
		$this->seed_wcs_subscriptions( 'paypal', $count );
		update_option( 'woocommerce_paypal_settings', array( 'enabled' => 'yes', '_should_load' => 'yes' ) );

		$this->sut->call_disable_legacy_paypal_standard_on_connect();

		// Transient must be set.
		$stored = get_transient( 'ppcp_wps_standard_subs_notice' );
		$this->assertSame( $count, (int) $stored );

		// Options must NOT be touched.
		$settings = get_option( 'woocommerce_paypal_settings' );
		$this->assertSame( 'yes', $settings['enabled'] );
	}

	/** @return array<string, array{int}> */
	public static function active_native_paypal_sub_counts(): array {
		return array(
			'one subscription'      => array( 1 ),
			'multiple subscriptions' => array( 5 ),
		);
	}

	/**
	 * @test
	 *
	 * GIVEN WCS is active with an active restore_paypal_standard subscription (no native WPS subs)
	 * WHEN  the merchant connects PPCP
	 * THEN  WPS is NOT disabled and a transient is stored
	 */
	public function test_stores_transient_when_restoration_plugin_subs_exist(): void {
		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			$this->markTestSkipped( 'WooCommerce Subscriptions not available.' );
		}

		$this->seed_wcs_subscriptions( 'restore_paypal_standard', 2 );
		update_option( 'woocommerce_paypal_settings', array( 'enabled' => 'yes', '_should_load' => 'yes' ) );

		$this->sut->call_disable_legacy_paypal_standard_on_connect();

		$stored = get_transient( 'ppcp_wps_standard_subs_notice' );
		$this->assertSame( 2, (int) $stored );

		$settings = get_option( 'woocommerce_paypal_settings' );
		$this->assertSame( 'yes', $settings['enabled'] );
	}

	/**
	 * @test
	 *
	 * GIVEN the restore-paypal-standard-for-woocommerce plugin is installed (option exists)
	 * WHEN  the merchant connects PPCP with no active subscriptions
	 * THEN  both woocommerce_paypal_settings and woocommerce_restore_paypal_standard_settings
	 *       have enabled set to 'no'
	 */
	public function test_also_disables_restoration_plugin_option_when_present(): void {
		update_option( 'woocommerce_paypal_settings', array( 'enabled' => 'yes', '_should_load' => 'yes' ) );
		update_option( 'woocommerce_restore_paypal_standard_settings', array( 'enabled' => 'yes' ) );

		$this->sut->call_disable_legacy_paypal_standard_on_connect();

		$wps   = get_option( 'woocommerce_paypal_settings' );
		$rpsfw = get_option( 'woocommerce_restore_paypal_standard_settings' );
		$this->assertSame( 'no', $wps['enabled'] );
		$this->assertSame( 'no', $wps['_should_load'] );
		$this->assertSame( 'no', $rpsfw['enabled'] );
	}

	/**
	 * @test
	 *
	 * GIVEN WPS is already fully disabled
	 * WHEN  the merchant re-connects PPCP
	 * THEN  the operation is idempotent (safe to write 'no' to already-'no' keys)
	 */
	public function test_disable_is_idempotent(): void {
		update_option( 'woocommerce_paypal_settings', array( 'enabled' => 'no', '_should_load' => 'no' ) );

		$this->sut->call_disable_legacy_paypal_standard_on_connect();
		$this->sut->call_disable_legacy_paypal_standard_on_connect();

		$settings = get_option( 'woocommerce_paypal_settings' );
		$this->assertSame( 'no', $settings['enabled'] );
		$this->assertSame( 'no', $settings['_should_load'] );
	}

	// ── maybe_show_wps_subscriptions_notice ───────────────────────────────

	/**
	 * @test
	 *
	 * GIVEN no transient has been set
	 * WHEN  admin_notices fires
	 * THEN  no output is produced
	 */
	public function test_no_output_when_transient_absent(): void {
		$output = $this->sut->call_maybe_show_wps_subscriptions_notice();

		$this->assertSame( '', $output );
	}

	/**
	 * @test
	 *
	 * GIVEN a subscription count transient is set
	 * WHEN  admin_notices fires
	 * THEN  a notice div is rendered and the transient is deleted
	 *
	 * @dataProvider subscription_count_notice_cases
	 */
	public function test_renders_notice_and_clears_transient( int $count, string $expected_fragment ): void {
		set_transient( 'ppcp_wps_standard_subs_notice', $count, 30 * DAY_IN_SECONDS );

		$output = $this->sut->call_maybe_show_wps_subscriptions_notice();

		$this->assertStringContainsString( 'notice notice-warning', $output );
		$this->assertStringContainsString( $expected_fragment, $output );
		// Transient must be deleted after display.
		$this->assertFalse( get_transient( 'ppcp_wps_standard_subs_notice' ) );
	}

	/** @return array<string, array{int, string}> */
	public static function subscription_count_notice_cases(): array {
		return array(
			'singular — 1 subscription'  => array( 1, '1 subscription</a> is still billed' ),
			'plural — 5 subscriptions'   => array( 5, '5 subscriptions</a> are still billed' ),
		);
	}

	// ── helpers ───────────────────────────────────────────────────────────

	/**
	 * Seeds WCS with the given number of active subscriptions for a gateway.
	 * No-op if WCS is not installed; callers should markTestSkipped() first.
	 */
	private function seed_wcs_subscriptions( string $gateway_id, int $count ): void {
		for ( $i = 0; $i < $count; $i++ ) {
			$order = wc_create_order();
			$sub   = wcs_create_subscription(
				array(
					'order_id'         => $order->get_id(),
					'status'           => 'active',
					'billing_interval' => 1,
					'billing_period'   => 'month',
				)
			);
			$sub->set_payment_method( $gateway_id );
			$sub->save();
		}
	}
}
