<?php

/**
 * Helper to get settings status.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
/**
 * Class SettingsStatus
 */
class SettingsStatus
{
    protected SettingsProvider $settings_provider;
    public function __construct(SettingsProvider $settings_provider)
    {
        $this->settings_provider = $settings_provider;
    }
    /**
     * Checks whether Pay Later messaging is enabled.
     */
    public function is_pay_later_messaging_enabled(): bool
    {
        return $this->settings_provider->pay_later_messaging_enabled();
    }
    /**
     * Check whether any Pay Later messaging location is enabled.
     */
    public function has_pay_later_messaging_locations(): bool
    {
        $selected_locations = $this->settings_provider->pay_later_messaging_locations();
        return !empty($selected_locations);
    }
    /**
     * Check whether Pay Later message is enabled for a given location.
     *
     * @param string $location The location setting name.
     * @return bool true if is enabled, otherwise false.
     */
    public function is_pay_later_messaging_enabled_for_location(string $location): bool
    {
        return $this->is_pay_later_messaging_enabled() && $this->has_pay_later_messaging_locations() && $this->is_location_enabled($this->settings_provider->pay_later_messaging_locations(), $location);
    }
    /**
     * Check whether Pay Later button is enabled either for checkout, cart or product page.
     *
     * @return bool true if is enabled, otherwise false.
     */
    public function is_pay_later_button_enabled(): bool
    {
        $pay_later_button_enabled = $this->settings_provider->pay_later_button_enabled();
        $selected_locations = $this->settings_provider->pay_later_button_locations();
        return $pay_later_button_enabled && !empty($selected_locations);
    }
    /**
     * Check whether Pay Later button is enabled for a given location.
     *
     * @param string $location The location.
     * @return bool true if is enabled, otherwise false.
     */
    public function is_pay_later_button_enabled_for_location(string $location): bool
    {
        $locations = $this->settings_provider->pay_later_button_locations();
        return $this->is_pay_later_button_enabled() && ($this->is_location_enabled($locations, $location) || 'product' === $location && $this->is_location_enabled($locations, 'mini-cart'));
    }
    /**
     * Checks whether smart buttons are enabled for a given location.
     *
     * @param string $location The location.
     * @return bool true if is enabled, otherwise false.
     */
    public function is_smart_button_enabled_for_location(string $location): bool
    {
        if ($location === 'block-editor') {
            $location = 'checkout-block';
        }
        $locations = $this->settings_provider->smart_button_locations();
        return $this->is_location_enabled($locations, $location);
    }
    /**
     * Adapts the context value to match the location settings.
     *
     * @param string $location The location/context.
     * @return string
     */
    protected function normalize_location(string $location): string
    {
        if ('pay-now' === $location) {
            $location = 'checkout';
        }
        if ('checkout-block' === $location) {
            $location = 'checkout-block-express';
        }
        if ('cart-block' === $location) {
            $location = 'cart';
        }
        return $location;
    }
    /**
     * Checks whether the location is in the list.
     *
     * @param array  $locations The list of enabled locations.
     * @param string $location The location to check.
     * @return bool
     */
    protected function is_location_enabled(array $locations, string $location): bool
    {
        $location = $this->normalize_location($location);
        $selected_locations = apply_filters('woocommerce_paypal_payments_selected_button_locations', $locations, 'locations');
        if (empty($selected_locations)) {
            return \false;
        }
        return in_array($location, $selected_locations, \true);
    }
}
