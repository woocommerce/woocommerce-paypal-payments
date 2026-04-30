<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Data;

use RuntimeException;
use WooCommerce\PayPalCommerce\Settings\DTO\PayLaterMessagingDTO;
use WooCommerce\PayPalCommerce\Settings\Service\DataSanitizer;
class PayLaterMessagingSettings extends \WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel
{
    protected const OPTION_KEY = 'woocommerce-ppcp-data-paylater-messaging';
    private const LEGACY_OPTION_KEY = 'woocommerce-ppcp-settings';
    protected DataSanitizer $sanitizer;
    /**
     * @throws RuntimeException If the OPTION_KEY is not defined in the child class.
     */
    public function __construct(DataSanitizer $sanitizer)
    {
        $this->sanitizer = $sanitizer;
        parent::__construct();
        $this->maybe_migrate_from_legacy();
    }
    protected function get_defaults(): array
    {
        return array('messaging_enabled' => \false, 'styling_per_location' => \true, 'messaging_locations' => array(), 'cart' => new PayLaterMessagingDTO('cart'), 'checkout' => new PayLaterMessagingDTO('checkout'), 'product' => new PayLaterMessagingDTO('product'), 'shop' => new PayLaterMessagingDTO('shop', \false, 'flex'), 'home' => new PayLaterMessagingDTO('home', \false, 'flex'), 'custom_placement' => new PayLaterMessagingDTO('custom_placement'));
    }
    public function get_messaging_enabled(): bool
    {
        return (bool) $this->data['messaging_enabled'];
    }
    public function set_messaging_enabled(bool $enabled): void
    {
        $this->data['messaging_enabled'] = $enabled;
    }
    public function get_styling_per_location(): bool
    {
        return (bool) $this->data['styling_per_location'];
    }
    public function set_styling_per_location(bool $enabled): void
    {
        $this->data['styling_per_location'] = $enabled;
    }
    public function get_messaging_locations(): array
    {
        return (array) $this->data['messaging_locations'];
    }
    public function set_messaging_locations(array $locations): void
    {
        $this->data['messaging_locations'] = $this->sanitizer->sanitize_array($locations, array($this->sanitizer, 'sanitize_text'));
    }
    public function get_cart(): PayLaterMessagingDTO
    {
        return $this->data['cart'];
    }
    public function set_cart($styles): void
    {
        $this->data['cart'] = $this->sanitize_paylater_messaging($styles, 'cart');
    }
    public function get_checkout(): PayLaterMessagingDTO
    {
        return $this->data['checkout'];
    }
    public function set_checkout($styles): void
    {
        $this->data['checkout'] = $this->sanitize_paylater_messaging($styles, 'checkout');
    }
    public function get_product(): PayLaterMessagingDTO
    {
        return $this->data['product'];
    }
    public function set_product($styles): void
    {
        $this->data['product'] = $this->sanitize_paylater_messaging($styles, 'product');
    }
    public function get_shop(): PayLaterMessagingDTO
    {
        return $this->data['shop'];
    }
    public function set_shop($styles): void
    {
        $this->data['shop'] = $this->sanitize_paylater_messaging($styles, 'shop');
    }
    public function get_home(): PayLaterMessagingDTO
    {
        return $this->data['home'];
    }
    public function set_home($styles): void
    {
        $this->data['home'] = $this->sanitize_paylater_messaging($styles, 'home');
    }
    public function get_custom_placement(): PayLaterMessagingDTO
    {
        return $this->data['custom_placement'];
    }
    public function set_custom_placement($styles): void
    {
        $this->data['custom_placement'] = $this->sanitize_paylater_messaging($styles, 'custom_placement');
    }
    /**
     * Returns an array representation of a location's messaging config in configurator format.
     *
     * @param string $location The location name.
     * @return array The configurator-compatible config array.
     */
    public function get_location_config(string $location): array
    {
        $method = "get_{$location}";
        if (!method_exists($this, $method)) {
            return array();
        }
        $dto = $this->{$method}();
        $enabled_locations = $this->get_messaging_locations();
        if ('shop' === $location || 'home' === $location) {
            return array('layout' => $dto->layout, 'color' => $dto->flex_color, 'ratio' => $dto->flex_ratio, 'status' => in_array($location, $enabled_locations, \true) ? 'enabled' : 'disabled', 'placement' => $location);
        }
        if ('custom_placement' === $location) {
            return array('status' => in_array('custom_placement', $enabled_locations, \true) ? 'enabled' : 'disabled', 'message_reference' => 'woocommerceBlock');
        }
        return array('layout' => $dto->layout, 'logo-position' => $dto->logo_position, 'logo-type' => $dto->logo_type, 'text-color' => $dto->text_color, 'text-size' => $dto->text_size, 'status' => in_array($location, $enabled_locations, \true) ? 'enabled' : 'disabled', 'placement' => $location);
    }
    /**
     * Updates a location's settings from configurator config array.
     *
     * @param string $location The location name.
     * @param array  $config The configurator config.
     */
    public function set_location_from_config(string $location, array $config): void
    {
        $method = "get_{$location}";
        if (!method_exists($this, $method)) {
            return;
        }
        $dto = $this->{$method}();
        if (isset($config['layout'])) {
            $dto->layout = $this->sanitizer->sanitize_text($config['layout'], 'text');
        }
        if (isset($config['logo-position'])) {
            $dto->logo_position = $this->sanitizer->sanitize_text($config['logo-position'], 'left');
        }
        if (isset($config['logo-type'])) {
            $dto->logo_type = $this->sanitizer->sanitize_text($config['logo-type'], 'inline');
        }
        if (isset($config['text-color']) || isset($config['logo-color'])) {
            $dto->text_color = $this->sanitizer->sanitize_text($config['text-color'] ?? $config['logo-color'] ?? 'black', 'black');
        }
        if (isset($config['text-size'])) {
            $dto->text_size = $this->sanitizer->sanitize_text($config['text-size'], '12');
        }
        if (isset($config['color'])) {
            $dto->flex_color = $this->sanitizer->sanitize_text($config['color'], 'black');
        }
        if (isset($config['ratio'])) {
            $dto->flex_ratio = $this->sanitizer->sanitize_text($config['ratio'], '8x1');
        }
        $setter = "set_{$location}";
        $this->{$setter}($dto);
    }
    /**
     * Sanitizes Pay Later messaging data.
     *
     * @param mixed   $data The messaging data to sanitize.
     * @param ?string $location Name of the location.
     * @return PayLaterMessagingDTO Sanitized messaging data.
     */
    private function sanitize_paylater_messaging($data, ?string $location = null): PayLaterMessagingDTO
    {
        if ($data instanceof PayLaterMessagingDTO) {
            if ($location) {
                $data->location = $location;
            }
            return $data;
        }
        if (is_object($data)) {
            $data = (array) $data;
        }
        if (!is_array($data)) {
            return new PayLaterMessagingDTO($location ?? '');
        }
        if (null === $location) {
            $location = $data['location'] ?? '';
        }
        $enabled_locations = $this->get_messaging_locations();
        return new PayLaterMessagingDTO($location, in_array($location, $enabled_locations, \true), $this->sanitizer->sanitize_text($data['layout'] ?? 'text'), $this->sanitizer->sanitize_text($data['logo_type'] ?? $data['logo-type'] ?? 'inline'), $this->sanitizer->sanitize_text($data['logo_position'] ?? $data['logo-position'] ?? 'left'), $this->sanitizer->sanitize_text($data['text_color'] ?? $data['text-color'] ?? 'black'), $this->sanitizer->sanitize_text($data['text_size'] ?? $data['text-size'] ?? '12'), $this->sanitizer->sanitize_text($data['flex_color'] ?? $data['color'] ?? 'black'), $this->sanitizer->sanitize_text($data['flex_ratio'] ?? $data['ratio'] ?? '8x1'));
    }
    private function maybe_migrate_from_legacy(): void
    {
        $has_new_data = get_option(static::OPTION_KEY);
        if (\false !== $has_new_data && !empty($has_new_data)) {
            return;
        }
        $legacy_settings = (array) get_option(self::LEGACY_OPTION_KEY, array());
        if (empty($legacy_settings)) {
            return;
        }
        $this->data['messaging_enabled'] = !empty($legacy_settings['pay_later_messaging_enabled']);
        $this->data['styling_per_location'] = !empty($legacy_settings['pay_later_enable_styling_per_messaging_location']);
        $locations = $legacy_settings['pay_later_messaging_locations'] ?? array();
        if (is_array($locations)) {
            $this->data['messaging_locations'] = $locations;
        }
        $location_map = array('cart' => 'cart', 'checkout' => 'checkout', 'product' => 'product', 'shop' => 'shop', 'home' => 'home', 'custom_placement' => 'custom_placement');
        foreach ($location_map as $location => $key) {
            $prefix = "pay_later_{$location}_message";
            $this->data[$key] = new PayLaterMessagingDTO($location, in_array($location, $this->data['messaging_locations'], \true), $legacy_settings["{$prefix}_layout"] ?? (in_array($location, array('shop', 'home'), \true) ? 'flex' : 'text'), $legacy_settings["{$prefix}_logo"] ?? 'inline', $legacy_settings["{$prefix}_position"] ?? 'left', $legacy_settings["{$prefix}_color"] ?? 'black', $legacy_settings["{$prefix}_text_size"] ?? '12', $legacy_settings["{$prefix}_flex_color"] ?? 'black', $legacy_settings["{$prefix}_flex_ratio"] ?? '8x1');
        }
        $this->save();
    }
}
