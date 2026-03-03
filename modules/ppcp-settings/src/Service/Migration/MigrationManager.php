<?php
/**
 * Provides functionality for settings migration management.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\Migration
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use Exception;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;

/**
 * Class MigrationManager
 *
 * Manages migration operations for plugin settings.
 */
class MigrationManager implements SettingsMigrationInterface {

	public const OPTION_NAME_MIGRATION_IS_DONE = 'woocommerce_ppcp-settings-migration-is-done';

	protected SettingsMigration $general_settings_migration;
	protected SettingsTabMigration $settings_tab_migration;
	protected StylingSettingsMigration $styling_settings_migration;
	protected PaymentSettingsMigration $payment_settings_migration;
	protected FastlaneSettingsMigration $fastlane_settings_migration;
	protected OnboardingProfile $onboarding_profile;
	protected LoggerInterface $logger;

	public function __construct(
		SettingsMigration $general_settings_migration,
		SettingsTabMigration $settings_tab_migration,
		StylingSettingsMigration $styling_settings_migration,
		PaymentSettingsMigration $payment_settings_migration,
		FastlaneSettingsMigration $fastlane_settings_migration,
		OnboardingProfile $onboarding_profile,
		LoggerInterface $logger
	) {
		$this->general_settings_migration  = $general_settings_migration;
		$this->settings_tab_migration      = $settings_tab_migration;
		$this->styling_settings_migration  = $styling_settings_migration;
		$this->payment_settings_migration  = $payment_settings_migration;
		$this->fastlane_settings_migration = $fastlane_settings_migration;
		$this->onboarding_profile          = $onboarding_profile;
		$this->logger                      = $logger;
	}

	public function migrate(): void {
		try {
			/**
			 * Clean up legacy UI toggle options that are no longer needed.
			 *
			 * These options were used to control whether merchants saw the old or new settings UI:
			 * - OPTION_NAME_SHOULD_USE_OLD_UI: Stored merchant's preference to use the old UI
			 * - woocommerce-ppcp-is-new-merchant: Flagged new merchants to bypass the old UI
			 *
			 * With the new settings UI now being the only interface, these options serve no purpose
			 * and are removed during the final migration to prevent confusion and reduce database bloat.
			 */
			delete_option( 'woocommerce_ppcp-settings-should-use-old-ui' );
			delete_option( 'woocommerce-ppcp-is-new-merchant' );

			$this->onboarding_profile->set_completed( true );
			$this->onboarding_profile->set_gateways_refreshed( true );
			$this->onboarding_profile->set_gateways_synced( true );
			$this->onboarding_profile->save();

			$this->general_settings_migration->migrate();
			$this->settings_tab_migration->migrate();
			$this->styling_settings_migration->migrate();
			$this->payment_settings_migration->migrate();
			$this->fastlane_settings_migration->migrate();

			update_option( self::OPTION_NAME_MIGRATION_IS_DONE, true );
		} catch ( Exception $error ) {
			$this->logger->warning(
				'Settings migration failed during transition to new UI',
				array(
					'error_message' => $error->getMessage(),
					'error_code'    => $error->getCode(),
					'trace'         => $error->getTraceAsString(),
				)
			);
		}
	}
}
