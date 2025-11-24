<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Tests\Integration\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\Settings\Service\Migration\MigrationManager;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsMigration;
use WooCommerce\PayPalCommerce\Tests\Integration\TestCase;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PartnersEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatus;
use WooCommerce\PayPalCommerce\ApiClient\Entity\SellerStatusCapability;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsTabMigration;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\StylingSettingsMigration;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\PaymentSettingsMigration;
use WooCommerce\PayPalCommerce\Compat\Settings\SettingsMapHelper;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;

abstract class AbstractMigrationManager extends TestCase {

	protected const OLD_SETTINGS_OPTION = 'woocommerce-ppcp-settings';
	protected const NEW_GENERAL_SETTINGS_OPTION = 'woocommerce-ppcp-data-common';
	protected const NEW_DATA_SETTINGS_OPTION = 'woocommerce-ppcp-data-settings';
	protected const NEW_STYLING_OPTION = 'woocommerce-ppcp-data-styling';
	protected const NEW_PAYMENT_OPTION = 'woocommerce-ppcp-data-payment';

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		add_filter( 'woocommerce.feature-flags.woocommerce_paypal_payments.settings_enabled', '__return_false' );
	}

	public static function tearDownAfterClass(): void {
		remove_filter( 'woocommerce.feature-flags.woocommerce_paypal_payments.settings_enabled', '__return_false' );
		parent::tearDownAfterClass();
	}

	protected function setUp(): void {
		parent::setUp();
		$this->deleteOptions();
	}

	public function tearDown(): void {
		$this->deleteOptions();
		parent::tearDown();
	}

	public function testMigrateOnboardedMerchantSettings(): void {
		$legacy_settings = $this->getLegacyOnboardedMerchantSettings();
		update_option( self::OLD_SETTINGS_OPTION, $legacy_settings );

		$container         = $this->getContainer();
		$migration_manager = $this->createMigrationManager( $container );

		$migration_manager->migrate();

		$this->assertNewGeneralSettings();
		$this->assertNewDataSettings();
		$this->assertNewStylingSettings();
		$this->assertNewPaymentSettings();
	}

	protected function deleteOptions(): void {
		delete_option( self::OLD_SETTINGS_OPTION );
		delete_option( self::NEW_GENERAL_SETTINGS_OPTION );
		delete_option( self::NEW_DATA_SETTINGS_OPTION );
		delete_option( self::NEW_STYLING_OPTION );
		delete_option( self::NEW_PAYMENT_OPTION );
	}

	protected function createMigrationManager( $container ): MigrationManager {
		$old_settings = $this->createOldSettingsInstance( $container );

		return new MigrationManager(
			$this->createSettingsMigration( $container, $old_settings ),
			$this->createSettingsTabMigration( $container, $old_settings ),
			$this->createStylingSettingsMigration( $container, $old_settings ),
			$this->createPaymentSettingsMigration( $container, $old_settings )
		);
	}

	protected function createSettingsMigration( $container, $old_settings ): SettingsMigration {
		$partners_endpoint = $this->createMock( PartnersEndpoint::class );
		$seller_status     = $this->createSellerStatusMock();

		$partners_endpoint->method( 'seller_status' )->willReturn( $seller_status );

		return new SettingsMigration(
			$old_settings,
			$container->get( 'settings.data.general' ),
			$partners_endpoint
		);
	}

	protected function createSettingsTabMigration( $container, $old_settings ) {
		return new SettingsTabMigration(
			$old_settings,
			$container->get( 'settings.data.settings' ),
			$container->get( 'compat.settings.settings_tab_map_helper' )
		);
	}

	protected function createStylingSettingsMigration( $container, $old_settings ) {
		return new StylingSettingsMigration(
			$old_settings,
			$container->get( 'settings.data.styling' )
		);
	}

	protected function createPaymentSettingsMigration( $container, $old_settings ) {
		return new PaymentSettingsMigration(
			$old_settings,
			$container->get( 'settings.data.payment' ),
			$container->get( 'api.helpers.dccapplies' ),
			$container->get( 'wcgateway.helper.dcc-product-status' ),
			$container->get( 'wcgateway.configuration.card-configuration' ),
			$container->get( 'ppcp-local-apms.payment-methods' )
		);
	}

	protected function createOldSettingsInstance( $container ) {
		$settings_map_helper = $this->createMock( SettingsMapHelper::class );
		$settings_map_helper->method( 'has_mapped_key' )->willReturn( false );
		$settings_map_helper->method( 'mapped_value' )->willReturn( null );

		return new Settings(
			[],
			'',
			[],
			[],
			$settings_map_helper
		);
	}

	abstract protected function getLegacyOnboardedMerchantSettings(): array;

	abstract protected function createSellerStatusMock(): SellerStatus;

	abstract protected function assertNewGeneralSettings(): void;

	abstract protected function assertNewDataSettings(): void;

	abstract protected function assertNewStylingSettings(): void;

	abstract protected function assertNewPaymentSettings(): void;

}
