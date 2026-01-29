<?php

/**
 * A helper for mapping old and new general settings.
 *
 * @package WooCommerce\PayPalCommerce\Compat\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\Settings;

/**
 * Handles mapping between old and new general settings.
 *
 * @psalm-import-type newSettingsKey from SettingsMap
 * @psalm-import-type oldSettingsKey from SettingsMap
 */
class GeneralSettingsMapHelper
{
    /**
     * Maps old setting keys to new setting keys.
     *
     * The new GeneralSettings class stores the current connection
     * details, without adding an environment-suffix (no `_sandbox`
     * or `_production` in the field name)
     * Only the `sandbox_merchant` flag indicates, which environment
     * the credentials are used for.
     *
     * @psalm-return array<oldSettingsKey, newSettingsKey>
     */
    public function map(): array
    {
        return array('merchant_id' => 'merchant_id', 'client_id' => 'client_id', 'client_secret' => 'client_secret', 'sandbox_on' => 'sandbox_merchant', 'live_client_id' => 'client_id', 'live_client_secret' => 'client_secret', 'live_merchant_id' => 'merchant_id', 'live_merchant_email' => 'merchant_email', 'merchant_email' => 'merchant_email', 'sandbox_client_id' => 'client_id', 'sandbox_client_secret' => 'client_secret', 'sandbox_merchant_id' => 'merchant_id', 'sandbox_merchant_email' => 'merchant_email', 'enabled' => '', 'allow_local_apm_gateways' => '');
    }
    /**
     * Retrieves the mapped value for the given key from the new settings.
     *
     * @param string                      $old_key The key from the legacy settings.
     * @param array<string, scalar|array> $settings_model The new settings model data as an array.
     * @return mixed The value of the mapped setting, or null if not applicable.
     */
    public function mapped_value(string $old_key, array $settings_model)
    {
        $settings_map = $this->map();
        $new_key = $settings_map[$old_key] ?? \false;
        switch ($old_key) {
            case 'enabled':
            case 'allow_local_apm_gateways':
                return \true;
            default:
                return $settings_model[$new_key] ?? null;
        }
    }
}
