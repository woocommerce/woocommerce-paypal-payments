<?php

/**
 * Handles migration of styling settings from legacy format to new structure.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service\Migration
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service\Migration;

use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Settings\Data\StylingSettings;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
/**
 * Class StylingSettingsMigration
 *
 * Handles migration of styling settings.
 */
class StylingSettingsMigration implements \WooCommerce\PayPalCommerce\Settings\Service\Migration\SettingsMigrationInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $settings;
    protected StylingSettings $styling_settings;
    public function __construct(array $settings, StylingSettings $styling_settings)
    {
        $this->settings = $settings;
        $this->styling_settings = $styling_settings;
    }
    public function migrate(): void
    {
        $location_styles = array();
        $styling_per_location = !empty($this->settings['smart_button_enable_styling_per_location']);
        foreach ($this->locations_map() as $old_location => $new_location) {
            $context = $styling_per_location ? $old_location : 'general';
            $location_styles[$new_location] = new LocationStylingDTO($new_location, $this->is_button_enabled_for_location($old_location, 'smart'), $this->enabled_methods($old_location), (string) ($this->style_for_context('shape', $context) ?? 'rect'), (string) ($this->style_for_context('label', $context) ?? 'pay'), (string) ($this->style_for_context('color', $context) ?? 'gold'), (string) ($this->style_for_context('layout', $context) ?? 'vertical'), (bool) ($this->style_for_context('tagline', $context) ?? \false));
        }
        $this->styling_settings->from_array($location_styles);
        $this->styling_settings->save();
    }
    /**
     * Determines which payment methods are enabled for a specific location.
     *
     * Checks legacy settings to determine which payment methods (PayPal, Pay Later,
     * Venmo, Apple Pay, Google Pay) should be enabled for the given location.
     *
     * @param string $location The location name ('cart', 'checkout', etc.).
     * @return string[] The list of enabled payment method IDs.
     */
    protected function enabled_methods(string $location): array
    {
        $methods = array(PayPalGateway::ID);
        if ($this->is_button_enabled_for_location($location, 'pay_later')) {
            $methods[] = 'pay-later';
        }
        if (isset($this->settings['disable_funding'])) {
            $disable_funding = $this->settings['disable_funding'];
            if (!in_array('venmo', $disable_funding, \true)) {
                $methods[] = 'venmo';
            }
        }
        if (!empty($this->settings['applepay_button_enabled'])) {
            $methods[] = ApplePayGateway::ID;
        }
        if (!empty($this->settings['googlepay_button_enabled'])) {
            $methods[] = GooglePayGateway::ID;
        }
        return $methods;
    }
    /**
     * Checks if a specific button type is enabled for a given location.
     *
     * @param string $location The location name ('cart', 'checkout', etc.).
     * @param string $type     The button type ('smart', 'pay_later', etc.).
     * @return bool True if the button is enabled for the location, false otherwise.
     */
    protected function is_button_enabled_for_location(string $location, string $type): bool
    {
        $key = "{$type}_button_locations";
        if (!isset($this->settings[$key])) {
            return \false;
        }
        $enabled_locations = $this->settings[$key];
        if ($location === 'cart') {
            return in_array($location, $enabled_locations, \true) || in_array('cart-block', $enabled_locations, \true);
        }
        return in_array($location, $enabled_locations, \true);
    }
    /**
     * Returns a mapping of old button location names to new settings location names.
     *
     * @return string[] The mapping of old location names to new location names.
     */
    protected function locations_map(): array
    {
        return array('product' => 'product', 'cart' => 'cart', 'checkout' => 'classic_checkout', 'mini-cart' => 'mini_cart', 'checkout-block-express' => 'express_checkout');
    }
    /**
     * Determines the style value for a given property in a given context.
     *
     * Looks up style values with context-specific fallbacks. For cart context,
     * also checks cart-block variants.
     *
     * @param string $style    The name of the style property ('shape', 'label', 'color', etc.).
     * @param string $location The location name ('cart', 'checkout', etc.).
     * @return string|bool|null The style value or null if not found.
     */
    private function style_for_context(string $style, string $location)
    {
        if ($location === 'cart') {
            return $this->get_style_value("button_{$location}_{$style}") ?? $this->get_style_value("button_cart-block_{$style}");
        }
        return $this->get_style_value("button_{$location}_{$style}") ?? $this->get_style_value("button_{$style}");
    }
    /**
     * Retrieves a style property value from legacy settings.
     *
     * @param string $key The style property key in the settings.
     * @return string|bool|null The style value or null if not found.
     */
    private function get_style_value(string $key)
    {
        if (!isset($this->settings[$key])) {
            return null;
        }
        return $this->settings[$key];
    }
}
