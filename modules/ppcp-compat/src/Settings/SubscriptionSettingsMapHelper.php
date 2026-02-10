<?php

/**
 * A helper for mapping old and new subscription settings.
 *
 * @package WooCommerce\PayPalCommerce\Compat\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\Settings;

use WooCommerce\PayPalCommerce\Settings\Data\Definition\FeaturesDefinition;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
/**
 * Handles mapping between old and new subscription settings.
 *
 * In the new settings UI, the Subscriptions mode value is set automatically based on the merchant type.
 * This class fakes the mapping and injects the appropriate value based on the merchant:
 * - Non-vaulting merchants will use PayPal Subscriptions.
 * - Merchants with vaulting will use PayPal Vaulting.
 * - Disabled subscriptions can be controlled using a filter.
 *
 * @psalm-import-type newSettingsKey from SettingsMap
 * @psalm-import-type oldSettingsKey from SettingsMap
 */
class SubscriptionSettingsMapHelper
{
    public const OLD_SETTINGS_SUBSCRIPTION_MODE_VALUE_VAULTING = 'vaulting_api';
    public const OLD_SETTINGS_SUBSCRIPTION_MODE_VALUE_SUBSCRIPTIONS = 'subscriptions_api';
    public const OLD_SETTINGS_SUBSCRIPTION_MODE_VALUE_DISABLED = 'disable_paypal_subscriptions';
    /**
     * The subscription helper.
     *
     * @var SubscriptionHelper $subscription_helper
     */
    protected SubscriptionHelper $subscription_helper;
    /**
     * Constructor.
     *
     * @param SubscriptionHelper $subscription_helper The subscription helper.
     */
    public function __construct(SubscriptionHelper $subscription_helper)
    {
        $this->subscription_helper = $subscription_helper;
    }
    /**
     * Maps the old subscription setting key.
     *
     * This method creates a placeholder mapping as this setting doesn't exist in the new settings.
     * The Subscriptions mode value is set automatically based on the merchant type.
     *
     * @psalm-return array<oldSettingsKey, newSettingsKey>
     */
    public function map(): array
    {
        return array('subscriptions_mode' => '');
    }
    /**
     * Retrieves the mapped value for the subscriptions_mode key from the new settings.
     *
     * @param string                      $old_key The key from the legacy settings.
     * @param array<string, scalar|array> $settings_model The new settings model data as an array.
     *
     * @return 'vaulting_api'|'subscriptions_api'|'disable_paypal_subscriptions'|null The mapped subscriptions_mode value, or null if not applicable.
     */
    public function mapped_value(string $old_key, array $settings_model): ?string
    {
        if ($old_key !== 'subscriptions_mode' || !$this->subscription_helper->plugin_is_active()) {
            return null;
        }
        $vaulting = $settings_model[FeaturesDefinition::FEATURE_SAVE_PAYPAL_AND_VENMO] ?? \false;
        $subscription_mode_value = $vaulting ? self::OLD_SETTINGS_SUBSCRIPTION_MODE_VALUE_VAULTING : self::OLD_SETTINGS_SUBSCRIPTION_MODE_VALUE_SUBSCRIPTIONS;
        /**
         * Allows disabling the subscription mode when using the new settings UI.
         *
         * @returns bool true if the subscription mode should be disabled, false otherwise (default is false).
         */
        $subscription_mode_disabled = (bool) apply_filters('woocommerce_paypal_payments_subscription_mode_disabled', \false);
        return $subscription_mode_disabled ? self::OLD_SETTINGS_SUBSCRIPTION_MODE_VALUE_DISABLED : $subscription_mode_value;
    }
}
