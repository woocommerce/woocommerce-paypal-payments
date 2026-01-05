<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Tests\Integration;

use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;

class PluginUpdateMigrationTest extends TestCase {
	private $original_version;

	public function setUp(): void {
		parent::setUp();

		$this->original_version = get_option( 'woocommerce-ppcp-version' );

		$timeout_cache = new Cache( 'ppcp-timeout' );
		$timeout_cache->delete( 'refresh_feature_status_timeout' );

		$failure_registry_cache = new Cache( 'ppcp-paypal-api-status-cache' );
		$failure_registry_cache->delete( 'failure_registry_seller_status' );

		$dcc_cache = new Cache( 'ppcp-paypal-dcc-status-cache' );
		$dcc_cache->delete( 'dcc_status_cache' );

		$pui_cache = new Cache( 'ppcp-paypal-pui-status-cache' );
		$pui_cache->delete( 'pui_status_cache' );
	}

	public function tearDown(): void {
		if ( $this->original_version ) {
			update_option( 'woocommerce-ppcp-version', $this->original_version );
		}

		parent::tearDown();
	}

	public function test_migration_on_update_clears_caches_and_resets_settings(): void {
		update_option( 'woocommerce-ppcp-version', '2.9.6' );

		// Simulates stale caches that would persist across plugin updates which could potentially
		// block fresh capability checks.
		$timeout_cache = new Cache( 'ppcp-timeout' );
		$timeout_cache->set( 'refresh_feature_status_timeout', time(), 60 );

		$failure_registry_cache = new Cache( 'ppcp-paypal-api-status-cache' );
		$failure_registry_cache->set( 'failure_registry_seller_status', time(), DAY_IN_SECONDS );

		$dcc_cache = new Cache( 'ppcp-paypal-dcc-status-cache' );
		$dcc_cache->set( 'dcc_status_cache', 'yes', MONTH_IN_SECONDS );

		$container       = $this->getContainer();
		$settings        = $container->get( 'wcgateway.settings' );
		$current_version = (string) $container->get( 'ppcp.plugin' )->getVersion();

		// Setting to false would create a "disabled" state could prevent triggering fresh API check,
		// the migration should reset these to empty string.
		$settings->set( 'products_dcc_enabled', false );
		$settings->set( 'products_pui_enabled', false );
		$settings->persist();

		update_option( 'woocommerce-ppcp-version', $current_version );
		do_action( 'woocommerce_paypal_payments_gateway_migrate', '2.9.6' );
		do_action( 'woocommerce_paypal_payments_gateway_migrate_on_update' );

		// Verify capability caches are cleared.
		$this->assertFalse( $timeout_cache->has( 'refresh_feature_status_timeout' ) );
		$this->assertFalse( $failure_registry_cache->has( 'failure_registry_seller_status' ) );

		// Verify settings reset to empty string (unknown state).
		$this->assertSame( '', $settings->get( 'products_dcc_enabled' ) );
		$this->assertSame( '', $settings->get( 'products_pui_enabled' ) );
	}
}
