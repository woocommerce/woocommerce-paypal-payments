<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\MigrationManager;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\PaymentSettingsMigration;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;

/**
 * Tests for PaymentSettingsMigration, specifically the allow_local_apm_gateways fix.
 */
class PaymentSettingsMigrationTest extends TestCase {

	protected const OLD_SETTINGS_OPTION = 'woocommerce-ppcp-settings';

	/**
	 * @var PaymentSettings
	 */
	private PaymentSettings $payment_settings;

	/**
	 * @var array
	 */
	private array $local_apms;

	public function setUp(): void {
		parent::setUp();
		$container              = $this->getContainer();
		$this->payment_settings = $container->get( 'settings.data.payment' );
		$this->local_apms       = $container->get( 'ppcp-local-apms.payment-methods' );
		$this->cleanUp();
	}

	public function tearDown(): void {
		$this->cleanUp();
		parent::tearDown();
	}

	private function cleanUp(): void {
		delete_option( self::OLD_SETTINGS_OPTION );
		delete_option( 'woocommerce-ppcp-data-payment' );
		$this->payment_settings = new PaymentSettings();
		delete_option( MigrationManager::OPTION_NAME_MIGRATION_IS_DONE );

		foreach ( $this->local_apms as $apm ) {
			delete_option( 'woocommerce_' . $apm['id'] . '_settings' );
		}
	}

	private function createMigration( array $settings ): PaymentSettingsMigration {
		$container = $this->getContainer();

		return new PaymentSettingsMigration(
			$settings,
			$this->payment_settings,
			$container->get( 'api.helpers.dccapplies' ),
			$container->get( 'wcgateway.helper.dcc-product-status' ),
			$container->get( 'wcgateway.configuration.card-configuration' ),
			$this->local_apms
		);
	}

	/**
	 * APMs should be enabled after migration even when allow_local_apm_gateways was false.
	 */
	public function testApmsEnabledWhenAllowLocalApmGatewaysFalse(): void {
		$settings = array(
			'allow_local_apm_gateways' => false,
			'disable_funding'          => array(),
		);

		$migration = $this->createMigration( $settings );
		$migration->migrate();

		foreach ( $this->local_apms as $apm ) {
			$this->assertTrue(
				$this->payment_settings->is_method_enabled( $apm['id'] ),
				"APM {$apm['id']} should be enabled when allow_local_apm_gateways is false and not in disable_funding."
			);
		}
	}

	/**
	 * APMs should be enabled after migration when allow_local_apm_gateways was true (existing behavior).
	 */
	public function testApmsEnabledWhenAllowLocalApmGatewaysTrue(): void {
		$settings = array(
			'allow_local_apm_gateways' => true,
			'disable_funding'          => array(),
		);

		$migration = $this->createMigration( $settings );
		$migration->migrate();

		foreach ( $this->local_apms as $apm ) {
			$this->assertTrue(
				$this->payment_settings->is_method_enabled( $apm['id'] ),
				"APM {$apm['id']} should be enabled when allow_local_apm_gateways is true."
			);
		}
	}

	/**
	 * APMs in disable_funding should remain disabled regardless of allow_local_apm_gateways.
	 */
	public function testApmsInDisableFundingStillExcluded(): void {
		$first_apm = reset( $this->local_apms );

		$settings = array(
			'allow_local_apm_gateways' => false,
			'disable_funding'          => array( $first_apm['id'] ),
		);

		$migration = $this->createMigration( $settings );
		$migration->migrate();

		$this->assertFalse(
			$this->payment_settings->is_method_enabled( $first_apm['id'] ),
			"APM {$first_apm['id']} should remain disabled when in disable_funding."
		);

		$remaining_apms = array_slice( $this->local_apms, 1 );
		foreach ( $remaining_apms as $apm ) {
			$this->assertTrue(
				$this->payment_settings->is_method_enabled( $apm['id'] ),
				"APM {$apm['id']} should be enabled when not in disable_funding."
			);
		}
	}

	/**
	 * Venmo should still be enabled when not in disable_funding.
	 */
	public function testVenmoEnabledWhenNotInDisableFunding(): void {
		$settings = array(
			'allow_local_apm_gateways' => false,
			'disable_funding'          => array(),
		);

		$migration = $this->createMigration( $settings );
		$migration->migrate();

		$this->assertTrue( $this->payment_settings->is_method_enabled( 'venmo' ) );
	}

	/**
	 * Venmo should not be enabled when in disable_funding.
	 */
	public function testVenmoDisabledWhenInDisableFunding(): void {
		$settings = array(
			'allow_local_apm_gateways' => false,
			'disable_funding'          => array( 'venmo' ),
		);

		$migration = $this->createMigration( $settings );
		$migration->migrate();

		$this->assertFalse( $this->payment_settings->is_method_enabled( 'venmo' ) );
	}
}
