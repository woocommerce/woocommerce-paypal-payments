<?php

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Compat;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \WooCommerce\PayPalCommerce\Compat\CompatModule::disable_legacy_paypal_standard_on_connect
 * @covers \WooCommerce\PayPalCommerce\Compat\CompatModule::maybe_show_wps_subscriptions_notice
 */
class DisableLegacyPaypalStandardTest extends TestCase {

	/** @var object Anonymous subclass of CompatModule */
	private object $sut;

	public function setUp(): void {
		parent::setUp();

		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 );
		}

		$this->sut = new class extends CompatModule {
			private ?int $wcs_count = null;

			public function set_wcs_count( ?int $count ): void {
				$this->wcs_count = $count;
			}

			protected function count_wps_active_subscriptions(): ?int {
				return $this->wcs_count;
			}

			public function call_disable(): void {
				$this->disable_legacy_paypal_standard_on_connect();
			}

			public function call_notice(): string {
				ob_start();
				$this->maybe_show_wps_subscriptions_notice();
				return ob_get_clean() ?: '';
			}
		};
	}

	// ── disable_legacy_paypal_standard_on_connect ──────────────────────────────

	/**
	 * WPS is disabled when WCS is absent (null) or has no active subscriptions (0).
	 *
	 * @test
	 * @dataProvider wcs_absent_or_empty_counts
	 */
	public function test_disables_wps_when_no_active_subscriptions( ?int $count ): void {
		$this->sut->set_wcs_count( $count );

		when( 'get_option' )->alias(
			static function ( string $key, $default = array() ) {
				if ( $key === 'woocommerce_paypal_settings' ) {
					return array( 'enabled' => 'yes', '_should_load' => 'yes' );
				}
				return $default;
			}
		);
		expect( 'update_option' )
			->once()
			->with( 'woocommerce_paypal_settings', array( 'enabled' => 'no', '_should_load' => 'no' ) );
		expect( 'set_transient' )->never();

		$this->sut->call_disable();
		$this->addToAssertionCount( 1 );
	}

	/** @return array<string, array{?int}> */
	public static function wcs_absent_or_empty_counts(): array {
		return array(
			'WCS not installed' => array( null ),
			'WCS has zero subs' => array( 0 ),
		);
	}

	/**
	 * WPS is NOT disabled and a transient is stored when active subscriptions exist.
	 *
	 * @test
	 * @dataProvider active_subscription_counts
	 */
	public function test_stores_transient_and_skips_disable_when_subscriptions_exist( int $count ): void {
		$this->sut->set_wcs_count( $count );

		expect( 'set_transient' )
			->once()
			->with( 'ppcp_wps_standard_subs_notice', $count, 30 * DAY_IN_SECONDS );
		expect( 'update_option' )->never();

		$this->sut->call_disable();
		$this->addToAssertionCount( 1 );
	}

	/** @return array<string, array{int}> */
	public static function active_subscription_counts(): array {
		return array(
			'one subscription'       => array( 1 ),
			'multiple subscriptions' => array( 5 ),
		);
	}

	/**
	 * Both native WPS and the restoration plugin option are disabled when the restore option exists.
	 *
	 * @test
	 */
	public function test_also_disables_restoration_plugin_when_option_present(): void {
		when( 'get_option' )->alias(
			static function ( string $key, $default = array() ) {
				if ( $key === 'woocommerce_paypal_settings' ) {
					return array( 'enabled' => 'yes', '_should_load' => 'yes' );
				}
				if ( $key === 'woocommerce_restore_paypal_standard_settings' ) {
					return array( 'enabled' => 'yes' );
				}
				return $default;
			}
		);
		expect( 'update_option' )
			->once()
			->with( 'woocommerce_paypal_settings', array( 'enabled' => 'no', '_should_load' => 'no' ) );
		expect( 'update_option' )
			->once()
			->with( 'woocommerce_restore_paypal_standard_settings', array( 'enabled' => 'no' ) );

		$this->sut->call_disable();
		$this->addToAssertionCount( 1 );
	}

	// ── maybe_show_wps_subscriptions_notice ────────────────────────────────────

	/**
	 * No output when the transient is absent.
	 *
	 * @test
	 */
	public function test_no_output_when_transient_absent(): void {
		when( 'get_transient' )->justReturn( false );

		$output = $this->sut->call_notice();

		$this->assertSame( '', $output );
	}

	/**
	 * A warning notice is rendered with the correct singular/plural count.
	 *
	 * @test
	 * @dataProvider subscription_count_notice_cases
	 */
	public function test_renders_notice_with_correct_count( int $count, string $expected_fragment ): void {
		when( 'get_transient' )->justReturn( $count );
		when( 'admin_url' )->returnArg();
		when( '_n' )->alias(
			static function ( string $single, string $plural, int $number ): string {
				return $number === 1 ? $single : $plural;
			}
		);

		$output = $this->sut->call_notice();

		$this->assertStringContainsString( 'notice notice-warning', $output );
		$this->assertStringContainsString( $expected_fragment, $output );
	}

	/** @return array<string, array{int, string}> */
	public static function subscription_count_notice_cases(): array {
		return array(
			'singular — 1 subscription' => array( 1, '1 subscription</a> is still billed' ),
			'plural — 5 subscriptions'  => array( 5, '5 subscriptions</a> are still billed' ),
		);
	}
}
