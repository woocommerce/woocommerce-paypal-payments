<?php

/**
 * The factory the Pay Later messaging configurator configs.
 *
 * @package WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayLaterConfigurator\Factory;

use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
/**
 * Class ConfigFactory.
 */
class ConfigFactory
{
    /**
     * Returns the configurator config for the given old settings.
     *
     * @param Settings $settings The settings.
     */
    public function from_settings(Settings $settings): array
    {
        return array('cart' => $this->for_location($settings, 'cart'), 'checkout' => $this->for_location($settings, 'checkout'), 'product' => $this->for_location($settings, 'product'), 'shop' => $this->for_location($settings, 'shop'), 'home' => $this->for_location($settings, 'home'), 'custom_placement' => array($this->for_location($settings, 'woocommerceBlock')));
    }
    /**
     * Returns the configurator config for a location.
     *
     * @param Settings $settings The settings.
     * @param string   $location The location name in the old settings.
     */
    private function for_location(Settings $settings, string $location): array
    {
        $selected_locations = $settings->has('pay_later_messaging_locations') ? $settings->get('pay_later_messaging_locations') : array();
        switch ($location) {
            case 'shop':
            case 'home':
                $config = $this->for_shop_or_home($settings, $location, $selected_locations);
                break;
            case 'woocommerceBlock':
                $config = $this->for_woocommerce_block($selected_locations);
                break;
            default:
                $config = $this->for_default_location($settings, $location, $selected_locations);
                break;
        }
        return $config;
    }
    /**
     * Returns the configurator config for shop, home locations.
     *
     * @param Settings $settings The settings.
     * @param string   $location The location.
     * @param string[] $selected_locations The list of selected locations.
     * @return array{
     *     layout: string,
     *     color: string,
     *     ratio: string,
     *     status: "disabled"|"enabled",
     *     placement: string
     * } The configurator config map.
     */
    private function for_shop_or_home(Settings $settings, string $location, array $selected_locations): array
    {
        return array('layout' => $this->get_or_default($settings, "pay_later_{$location}_message_layout", 'flex'), 'color' => $this->get_or_default($settings, "pay_later_{$location}_message_flex_color", 'black'), 'ratio' => $this->get_or_default($settings, "pay_later_{$location}_message_flex_ratio", '8x1'), 'status' => in_array($location, $selected_locations, \true) ? 'enabled' : 'disabled', 'placement' => $location);
    }
    /**
     * Returns the configurator config for woocommerceBlock location.
     *
     * @param array $selected_locations The list of selected locations.
     * @return array{
     *     status: "disabled"|"enabled",
     *     message_reference: string
     * } The configurator config map.
     */
    private function for_woocommerce_block(array $selected_locations): array
    {
        return array('status' => in_array('custom_placement', $selected_locations, \true) ? 'enabled' : 'disabled', 'message_reference' => 'woocommerceBlock');
    }
    /**
     * Returns the configurator config for default locations.
     *
     * @param Settings $settings The settings.
     * @param string   $location The location.
     * @param string[] $selected_locations The list of selected locations.
     * @return array{
     *     layout: string,
     *     logo-position: string,
     *     logo-type: string,
     *     text-color: string,
     *     text-size: string,
     *     status: "disabled"|"enabled",
     *     placement: string
     * } The configurator config map.
     */
    private function for_default_location(Settings $settings, string $location, array $selected_locations): array
    {
        return array('layout' => $this->get_or_default($settings, "pay_later_{$location}_message_layout", 'text'), 'logo-position' => $this->get_or_default($settings, "pay_later_{$location}_message_position", 'left'), 'logo-type' => $this->get_or_default($settings, "pay_later_{$location}_message_logo", 'inline'), 'text-color' => $this->get_or_default($settings, "pay_later_{$location}_message_color", 'black'), 'text-size' => $this->get_or_default($settings, "pay_later_{$location}_message_text_size", '12'), 'status' => in_array($location, $selected_locations, \true) ? 'enabled' : 'disabled', 'placement' => $location);
    }
    /**
     * Returns the settings value or default, if does not exist or not allowed value.
     *
     * @param Settings   $settings The settings.
     * @param string     $key The key.
     * @param mixed      $default The default value.
     * @param array|null $allowed_values The list of allowed values, or null if all values are allowed.
     * @return string
     */
    private function get_or_default(Settings $settings, string $key, $default, ?array $allowed_values = null): string
    {
        if ($settings->has($key)) {
            $value = $settings->get($key);
            if (!$allowed_values || in_array($value, $allowed_values, \true)) {
                return $value;
            }
        }
        return $default;
    }
}
