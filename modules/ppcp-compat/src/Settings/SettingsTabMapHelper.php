<?php

/**
 * A helper for mapping the old/new settings tab settings.
 *
 * @package WooCommerce\PayPalCommerce\Compat\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\Settings;

use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PurchaseUnitSanitizer;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
/**
 * A map of old to new styling settings.
 *
 * @psalm-import-type newSettingsKey from SettingsMap
 * @psalm-import-type oldSettingsKey from SettingsMap
 */
class SettingsTabMapHelper
{
    /**
     * A map of new to old 3d secure values.
     *
     * @var array<string, string>
     */
    public const THREE_D_SECURE_VALUES_MAP = array('no-3d-secure' => 'NO_3D_SECURE', 'only-required-3d-secure' => 'SCA_WHEN_REQUIRED', 'always-3d-secure' => 'SCA_ALWAYS');
    /**
     * Maps old setting keys to new setting keys.
     *
     * @psalm-return array<oldSettingsKey, newSettingsKey>
     */
    public function map(): array
    {
        return array('disable_cards' => 'disabled_cards', 'brand_name' => 'brand_name', 'soft_descriptor' => 'soft_descriptor', 'payee_preferred' => 'instant_payments_only', 'subtotal_mismatch_behavior' => 'subtotal_adjustment', 'landing_page' => 'landing_page', 'smart_button_language' => 'button_language', 'prefix' => 'invoice_prefix', 'intent' => '', 'vault_enabled_dcc' => 'save_card_details', 'blocks_final_review_enabled' => 'enable_pay_now', 'logging_enabled' => 'enable_logging', 'vault_enabled' => FeaturesDefinition::FEATURE_SAVE_PAYPAL_AND_VENMO, '3d_secure_contingency' => 'three_d_secure', 'stay_updated' => 'stay_updated');
    }
    /**
     * Retrieves the value of a mapped key from the new settings.
     *
     * @param string                      $old_key The key from the legacy settings.
     * @param array<string, scalar|array> $settings_model The new settings model data as an array.
     * @return mixed The value of the mapped setting, (null if not found).
     */
    public function mapped_value(string $old_key, array $settings_model)
    {
        $settings_map = $this->map();
        $new_key = $settings_map[$old_key] ?? \false;
        switch ($old_key) {
            case 'subtotal_mismatch_behavior':
                return $this->mapped_mismatch_behavior_value($settings_model);
            case 'landing_page':
                return $this->mapped_landing_page_value($settings_model);
            case 'intent':
                return $this->mapped_intent_value($settings_model);
            case 'blocks_final_review_enabled':
                return $this->mapped_pay_now_value($settings_model);
            case '3d_secure_contingency':
                return $this->mapped_3d_secure_value($settings_model);
            default:
                return $settings_model[$new_key] ?? null;
        }
    }
    /**
     * Retrieves the mapped value for the '3d_secure_contingency' from the new settings.
     *
     * @param array $settings_model The new settings model data.
     * @return string|null The mapped '3d_secure_contingency' setting value.
     */
    protected function mapped_3d_secure_value(array $settings_model): ?string
    {
        $three_d_secure = $settings_model['three_d_secure'] ?? null;
        if (!is_string($three_d_secure)) {
            return null;
        }
        return self::THREE_D_SECURE_VALUES_MAP[$three_d_secure] ?? null;
    }
    /**
     * Retrieves the mapped value for the 'mismatch_behavior' from the new settings.
     *
     * @param array<string, scalar|array> $settings_model The new settings model data as an array.
     * @return 'extra_line'|'ditch'|null The mapped 'mismatch_behavior' setting value.
     */
    protected function mapped_mismatch_behavior_value(array $settings_model): ?string
    {
        $subtotal_adjustment = $settings_model['subtotal_adjustment'] ?? \false;
        if (!$subtotal_adjustment) {
            return null;
        }
        return $subtotal_adjustment === 'correction' ? PurchaseUnitSanitizer::MODE_EXTRA_LINE : PurchaseUnitSanitizer::MODE_DITCH;
    }
    /**
     * Retrieves the mapped value for the 'landing_page' from the new settings.
     *
     * @param array<string, scalar|array> $settings_model The new settings model data as an array.
     * @return 'LOGIN'|'GUEST_CHECKOUT'|'NO_PREFERENCE'|null The mapped 'landing_page' setting value.
     */
    protected function mapped_landing_page_value(array $settings_model): ?string
    {
        $landing_page = $settings_model['landing_page'] ?? \false;
        if (!$landing_page) {
            return null;
        }
        return $landing_page === 'login' ? ExperienceContext::LANDING_PAGE_LOGIN : ($landing_page === 'guest_checkout' ? ExperienceContext::LANDING_PAGE_GUEST_CHECKOUT : ExperienceContext::LANDING_PAGE_NO_PREFERENCE);
    }
    /**
     * Retrieves the mapped value for the order intent from the new settings.
     *
     * @param array<string, scalar|array> $settings_model The new settings model data as an array.
     * @return 'AUTHORIZE'|'CAPTURE'|null The mapped 'intent' setting value.
     */
    protected function mapped_intent_value(array $settings_model): ?string
    {
        $authorize_only = $settings_model['authorize_only'] ?? null;
        $capture_virtual_orders = $settings_model['capture_virtual_orders'] ?? null;
        if (is_null($authorize_only) && is_null($capture_virtual_orders)) {
            return null;
        }
        return $authorize_only ? 'AUTHORIZE' : 'CAPTURE';
    }
    /**
     * Retrieves the mapped value for the "Pay Now Experience" from the new settings.
     *
     * @param array<string, scalar|array> $settings_model The new settings model data as an array.
     * @return bool|null The mapped 'Pay Now Experience' setting value.
     */
    protected function mapped_pay_now_value(array $settings_model): ?bool
    {
        $enable_pay_now = $settings_model['enable_pay_now'] ?? null;
        if (is_null($enable_pay_now)) {
            return null;
        }
        return !$enable_pay_now;
    }
}
