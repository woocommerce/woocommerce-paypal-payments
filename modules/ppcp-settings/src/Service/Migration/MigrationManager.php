<?php

/**
 * Provides functionality for settings migration management.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\Migration
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;
/**
 * Class MigrationManager
 *
 * Manages migration operations for plugin settings.
 */
class MigrationManager implements \WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsMigrationInterface
{
    public const OPTION_NAME_MIGRATION_IS_DONE = 'woocommerce_ppcp-settings-migration-is-done';
    protected \WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsMigration $general_settings_migration;
    protected \WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsTabMigration $settings_tab_migration;
    protected \WooCommerce\PayPalCommerce\Settings\Service\Migration\StylingSettingsMigration $styling_settings_migration;
    protected \WooCommerce\PayPalCommerce\Settings\Service\Migration\PaymentSettingsMigration $payment_settings_migration;
    protected \WooCommerce\PayPalCommerce\Settings\Service\Migration\FastlaneSettingsMigration $fastlane_settings_migration;
    protected OnboardingProfile $onboarding_profile;
    protected LoggerInterface $logger;
    public function __construct(\WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsMigration $general_settings_migration, \WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsTabMigration $settings_tab_migration, \WooCommerce\PayPalCommerce\Settings\Service\Migration\StylingSettingsMigration $styling_settings_migration, \WooCommerce\PayPalCommerce\Settings\Service\Migration\PaymentSettingsMigration $payment_settings_migration, \WooCommerce\PayPalCommerce\Settings\Service\Migration\FastlaneSettingsMigration $fastlane_settings_migration, OnboardingProfile $onboarding_profile, LoggerInterface $logger)
    {
        $this->general_settings_migration = $general_settings_migration;
        $this->settings_tab_migration = $settings_tab_migration;
        $this->styling_settings_migration = $styling_settings_migration;
        $this->payment_settings_migration = $payment_settings_migration;
        $this->fastlane_settings_migration = $fastlane_settings_migration;
        $this->onboarding_profile = $onboarding_profile;
        $this->logger = $logger;
    }
    public function migrate(): void
    {
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
        delete_option('woocommerce_ppcp-settings-should-use-old-ui');
        delete_option('woocommerce-ppcp-is-new-merchant');
        $this->onboarding_profile->set_completed(\true);
        $this->onboarding_profile->set_gateways_refreshed(\true);
        $this->onboarding_profile->set_gateways_synced(\true);
        $this->onboarding_profile->save();
        $migrations = array('general_settings' => $this->general_settings_migration, 'settings_tab' => $this->settings_tab_migration, 'styling' => $this->styling_settings_migration, 'payment' => $this->payment_settings_migration, 'fastlane' => $this->fastlane_settings_migration);
        foreach ($migrations as $name => $migration) {
            try {
                $migration->migrate();
            } catch (Exception $error) {
                $this->logger->warning("Settings migration failed for '{$name}' during transition to new UI", array('error_message' => $error->getMessage(), 'error_code' => $error->getCode(), 'trace' => $error->getTraceAsString()));
            }
        }
        update_option(self::OPTION_NAME_MIGRATION_IS_DONE, \true);
        /**
         * Clear product status caches that may have been poisoned during migration.
         *
         * The PartnersEndpoint call in SettingsMigration may use the wrong environment
         * (production instead of sandbox) before sandbox_merchant is set, causing
         * stale false values in reference_transaction and other caches.
         */
        do_action('woocommerce_paypal_payments_clear_apm_product_status');
    }
}
