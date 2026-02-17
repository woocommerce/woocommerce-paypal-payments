<?php

/**
 * The settings UI switching Ajax endpoint.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Ajax;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;
use WooCommerce\PayPalCommerce\Settings\Service\Migration\MigrationManager;
/**
 * Class SwitchSettingsUiEndpoint
 *
 * Note: This is an ajax handler, not a REST endpoint
 */
class SwitchSettingsUiEndpoint
{
    public const ENDPOINT = 'ppcp-settings-switch-ui';
    public const OPTION_NAME_SHOULD_USE_OLD_UI = 'woocommerce_ppcp-settings-should-use-old-ui';
    public const OPTION_NAME_MIGRATION_IS_DONE = 'woocommerce_ppcp-settings-migration-is-done';
    protected RequestData $request_data;
    protected LoggerInterface $logger;
    protected OnboardingProfile $onboarding_profile;
    protected MigrationManager $settings_data_migration;
    /**
     * True if the merchant is onboarded, otherwise false.
     *
     * @var bool
     */
    protected bool $is_onboarded;
    public function __construct(LoggerInterface $logger, RequestData $request_data, OnboardingProfile $onboarding_profile, MigrationManager $settings_data_migration, bool $is_onboarded)
    {
        $this->logger = $logger;
        $this->request_data = $request_data;
        $this->onboarding_profile = $onboarding_profile;
        $this->settings_data_migration = $settings_data_migration;
        $this->is_onboarded = $is_onboarded;
    }
    /**
     * Handles the request.
     */
    public function handle_request(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Not an admin.', 403);
            return;
        }
        try {
            $this->request_data->read_request($this->nonce());
            update_option(self::OPTION_NAME_SHOULD_USE_OLD_UI, 'no');
            $this->onboarding_profile->set_completed(\true);
            $this->onboarding_profile->set_gateways_refreshed(\true);
            $this->onboarding_profile->set_gateways_synced(\true);
            $this->onboarding_profile->save();
            $this->settings_data_migration->migrate();
            update_option(self::OPTION_NAME_MIGRATION_IS_DONE, \true);
            wp_send_json_success();
        } catch (Exception $error) {
            wp_send_json_error(array('message' => $error->getMessage()), 500);
        }
    }
    /**
     * The nonce.
     *
     * @return string
     */
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
}
