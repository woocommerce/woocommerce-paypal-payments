<?php
/**
 * Provides functionality for settings migration management.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\Migration
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

/**
 * Class MigrationManager
 *
 * Manages migration operations for plugin settings.
 */
class MigrationManager implements SettingsMigrationInterface {

	protected SettingsMigration $general_settings_migration;
	protected SettingsTabMigration $settings_tab_migration;
	protected StylingSettingsMigration $styling_settings_migration;
	protected PaymentSettingsMigration $payment_settings_migration;

	public function __construct(
		SettingsMigration $general_settings_migration,
		SettingsTabMigration $settings_tab_migration,
		StylingSettingsMigration $styling_settings_migration,
		PaymentSettingsMigration $payment_settings_migration
	) {
		$this->general_settings_migration = $general_settings_migration;
		$this->settings_tab_migration     = $settings_tab_migration;
		$this->styling_settings_migration = $styling_settings_migration;
		$this->payment_settings_migration = $payment_settings_migration;
	}

	public function migrate(): void {
		$this->general_settings_migration->migrate();
		$this->settings_tab_migration->migrate();
		$this->styling_settings_migration->migrate();
		$this->payment_settings_migration->migrate();
	}
}
