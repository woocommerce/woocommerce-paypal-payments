<?php

/**
 * A helper for mapping the new/old settings.
 *
 * @package WooCommerce\PayPalCommerce\Compat\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\Settings;

use RuntimeException;
use WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Data\StylingSettings;
/**
 * A helper class to manage the transition between legacy and new settings.
 *
 * This utility provides mapping from old setting keys to new ones and retrieves
 * their corresponding values from the appropriate models. The class uses lazy
 * loading and caching to optimize performance during runtime.
 */
class SettingsMapHelper
{
    /**
     * A list of settings maps containing mapping definitions.
     *
     * @var SettingsMap[]
     */
    protected array $settings_map;
    /**
     * Indexed map for faster lookups, initialized lazily.
     *
     * @var array|null Associative array where old keys map to metadata.
     */
    protected ?array $key_to_model = null;
    /**
     * Cache for results of `to_array()` calls on models.
     *
     * @var array Associative array where keys are model IDs.
     */
    protected array $model_cache = array();
    /**
     * A helper for mapping the old/new styling settings.
     *
     * @var StylingSettingsMapHelper
     */
    protected \WooCommerce\PayPalCommerce\Compat\Settings\StylingSettingsMapHelper $styling_settings_map_helper;
    /**
     * A helper for mapping the old/new settings tab settings.
     *
     * @var SettingsTabMapHelper
     */
    protected \WooCommerce\PayPalCommerce\Compat\Settings\SettingsTabMapHelper $settings_tab_map_helper;
    /**
     * A helper for mapping old and new subscription settings.
     *
     * @var SubscriptionSettingsMapHelper
     */
    protected \WooCommerce\PayPalCommerce\Compat\Settings\SubscriptionSettingsMapHelper $subscription_map_helper;
    /**
     * A helper for mapping old and new general settings.
     *
     * @var GeneralSettingsMapHelper
     */
    protected \WooCommerce\PayPalCommerce\Compat\Settings\GeneralSettingsMapHelper $general_settings_map_helper;
    /**
     * A helper for mapping old and new payment method settings.
     *
     * @var PaymentMethodSettingsMapHelper
     */
    protected \WooCommerce\PayPalCommerce\Compat\Settings\PaymentMethodSettingsMapHelper $payment_method_settings_map_helper;
    /**
     * Whether the new settings module is enabled.
     *
     * @var bool
     */
    protected bool $new_settings_module_enabled;
    /**
     * Constructor.
     *
     * @param SettingsMap[]                  $settings_map A list of settings maps containing key definitions.
     * @param StylingSettingsMapHelper       $styling_settings_map_helper A helper for mapping the old/new styling settings.
     * @param SettingsTabMapHelper           $settings_tab_map_helper A helper for mapping the old/new settings tab settings.
     * @param SubscriptionSettingsMapHelper  $subscription_map_helper A helper for mapping old and new subscription settings.
     * @param GeneralSettingsMapHelper       $general_settings_map_helper A helper for mapping old and new general settings.
     * @param PaymentMethodSettingsMapHelper $payment_method_settings_map_helper A helper for mapping old and new payment method settings.
     * @param bool                           $new_settings_module_enabled Whether the new settings module is enabled.
     * @throws RuntimeException When an old key has multiple mappings.
     */
    public function __construct(array $settings_map, \WooCommerce\PayPalCommerce\Compat\Settings\StylingSettingsMapHelper $styling_settings_map_helper, \WooCommerce\PayPalCommerce\Compat\Settings\SettingsTabMapHelper $settings_tab_map_helper, \WooCommerce\PayPalCommerce\Compat\Settings\SubscriptionSettingsMapHelper $subscription_map_helper, \WooCommerce\PayPalCommerce\Compat\Settings\GeneralSettingsMapHelper $general_settings_map_helper, \WooCommerce\PayPalCommerce\Compat\Settings\PaymentMethodSettingsMapHelper $payment_method_settings_map_helper, bool $new_settings_module_enabled)
    {
        $this->validate_settings_map($settings_map);
        $this->settings_map = $settings_map;
        $this->styling_settings_map_helper = $styling_settings_map_helper;
        $this->settings_tab_map_helper = $settings_tab_map_helper;
        $this->subscription_map_helper = $subscription_map_helper;
        $this->general_settings_map_helper = $general_settings_map_helper;
        $this->payment_method_settings_map_helper = $payment_method_settings_map_helper;
        $this->new_settings_module_enabled = $new_settings_module_enabled;
    }
    /**
     * Validates the settings map for duplicate keys.
     *
     * @param SettingsMap[] $settings_map The settings map to validate.
     * @throws RuntimeException When an old key has multiple mappings.
     */
    protected function validate_settings_map(array $settings_map): void
    {
        $seen_keys = array();
        foreach ($settings_map as $settings_map_instance) {
            foreach ($settings_map_instance->get_map() as $old_key => $new_key) {
                if (isset($seen_keys[$old_key])) {
                    throw new RuntimeException("Duplicate mapping for legacy key '{$old_key}'.");
                }
                $seen_keys[$old_key] = \true;
            }
        }
    }
    /**
     * Retrieves the value of a mapped key from the new settings.
     *
     * @param string $old_key The key from the legacy settings.
     *
     * @return mixed|null The value of the mapped setting, or null if not found.
     */
    public function mapped_value(string $old_key)
    {
        if (!$this->new_settings_module_enabled) {
            return null;
        }
        $this->ensure_map_initialized();
        if (!isset($this->key_to_model[$old_key])) {
            return null;
        }
        $mapping = $this->key_to_model[$old_key];
        $model = $mapping['model'] ?? \false;
        if (!$model) {
            return null;
        }
        return $this->get_cached_model_value(spl_object_id($model), $old_key, $mapping['new_key'], $mapping['model']);
    }
    /**
     * Determines if a given legacy key exists in the new settings.
     *
     * @param string $old_key The key from the legacy settings.
     *
     * @return bool True if the key exists in the new settings, false otherwise.
     */
    public function has_mapped_key(string $old_key): bool
    {
        if (!$this->new_settings_module_enabled) {
            return \false;
        }
        $this->ensure_map_initialized();
        return isset($this->key_to_model[$old_key]);
    }
    /**
     * Retrieves a cached model value or caches it if not already cached.
     *
     * @param int    $model_id The unique identifier for the model object.
     * @param string $old_key  The key in the old settings structure.
     * @param string $new_key  The key in the new settings structure.
     * @param object $model    The model object.
     *
     * @return mixed|null The value of the key in the model, or null if not found.
     */
    protected function get_cached_model_value(int $model_id, string $old_key, string $new_key, object $model)
    {
        if (!isset($this->model_cache[$model_id])) {
            $this->model_cache[$model_id] = $model->to_array();
        }
        switch (\true) {
            case $model instanceof StylingSettings:
                return $this->styling_settings_map_helper->mapped_value($old_key, $this->model_cache[$model_id], $this->get_payment_settings_model());
            case $model instanceof GeneralSettings:
                return $this->general_settings_map_helper->mapped_value($old_key, $this->model_cache[$model_id]);
            case $model instanceof SettingsModel:
                return $old_key === 'subscriptions_mode' ? $this->subscription_map_helper->mapped_value($old_key, $this->model_cache[$model_id]) : $this->settings_tab_map_helper->mapped_value($old_key, $this->model_cache[$model_id]);
            case $model instanceof PaymentSettings:
                return $this->payment_method_settings_map_helper->mapped_value($old_key, $this->get_payment_settings_model());
            default:
                return $this->model_cache[$model_id][$new_key] ?? null;
        }
    }
    /**
     * Ensures the map of old-to-new settings is initialized.
     *
     * This method initializes the `key_to_model` array lazily to improve performance.
     *
     * @return void
     */
    protected function ensure_map_initialized(): void
    {
        if ($this->key_to_model === null) {
            $this->initialize_key_map();
        }
    }
    /**
     * Initializes the indexed map of old-to-new settings keys.
     *
     * This method processes the provided settings maps and indexes the legacy
     * keys to their corresponding metadata for efficient lookup.
     *
     * @return void
     */
    protected function initialize_key_map(): void
    {
        $this->key_to_model = array();
        foreach ($this->settings_map as $settings_map_instance) {
            foreach ($settings_map_instance->get_map() as $old_key => $new_key) {
                $this->key_to_model[$old_key] = array('new_key' => $new_key, 'model' => $settings_map_instance->get_model());
            }
        }
    }
    /**
     * Retrieves the PaymentSettings model instance.
     *
     * Once the new settings module is permanently enabled,
     * this model can be passed as a dependency to the appropriate helper classes.
     * For now, we must pass it this way to avoid errors when the new settings module is disabled.
     *
     * @return AbstractDataModel|null
     */
    protected function get_payment_settings_model(): ?AbstractDataModel
    {
        foreach ($this->settings_map as $settings_map_instance) {
            if ($settings_map_instance->get_model() instanceof PaymentSettings) {
                return $settings_map_instance->get_model();
            }
        }
        return null;
    }
}
