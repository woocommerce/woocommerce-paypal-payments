<?php

/**
 * The settings object.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\Compat\Settings\SettingsMapHelper;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
/**
 * Class Settings
 */
class Settings implements ContainerInterface
{
    const KEY = 'woocommerce-ppcp-settings';
    const CONNECTION_TAB_ID = 'ppcp-connection';
    const PAY_LATER_TAB_ID = 'ppcp-pay-later';
    /**
     * The settings.
     *
     * @var array
     */
    private array $settings = array();
    /**
     * The list of selected default button locations.
     *
     * @var string[]
     */
    protected array $default_button_locations;
    /**
     * The list of selected default pay later button locations.
     *
     * @var string[]
     */
    protected array $default_pay_later_button_locations;
    /**
     * The list of selected default pay later messaging locations.
     *
     * @var string[]
     */
    protected array $default_pay_later_messaging_locations;
    /**
     * The default ACDC gateway title.
     *
     * @var string
     */
    protected string $default_dcc_gateway_title;
    /**
     * A helper for mapping the new/old settings.
     *
     * @var SettingsMapHelper
     */
    protected SettingsMapHelper $settings_map_helper;
    /**
     * Settings constructor.
     *
     * @param string[]          $default_button_locations              The list of selected default
     *                                                                 button locations.
     * @param string            $default_dcc_gateway_title             The default ACDC gateway
     *                                                                 title.
     * @param string[]          $default_pay_later_button_locations    The list of selected default
     *                                                                 pay later button locations.
     * @param string[]          $default_pay_later_messaging_locations The list of selected default
     *                                                                 pay later messaging
     *                                                                 locations.
     * @param SettingsMapHelper $settings_map_helper                   A helper for mapping the
     *                                                                 new/old settings.
     */
    public function __construct(array $default_button_locations, string $default_dcc_gateway_title, array $default_pay_later_button_locations, array $default_pay_later_messaging_locations, SettingsMapHelper $settings_map_helper)
    {
        $this->default_button_locations = $default_button_locations;
        $this->default_dcc_gateway_title = $default_dcc_gateway_title;
        $this->default_pay_later_button_locations = $default_pay_later_button_locations;
        $this->default_pay_later_messaging_locations = $default_pay_later_messaging_locations;
        $this->settings_map_helper = $settings_map_helper;
    }
    /**
     * Returns the value for an id.
     *
     * @throws NotFoundException When nothing was found.
     *
     * @param string $id The value identifier.
     *
     * @return mixed
     */
    public function get($id)
    {
        if (!$this->has($id)) {
            throw new NotFoundException();
        }
        return $this->settings_map_helper->mapped_value($id) ?? $this->settings[$id];
    }
    /**
     * Whether a value exists.
     *
     * @param string $id The value identifier.
     *
     * @return bool
     */
    public function has(string $id)
    {
        if ($this->settings_map_helper->has_mapped_key($id) && !is_null($this->settings_map_helper->mapped_value($id))) {
            return \true;
        }
        $this->load();
        return array_key_exists($id, $this->settings);
    }
    /**
     * Sets a value.
     *
     * @param string $id    The value identifier.
     * @param mixed  $value The value.
     */
    public function set($id, $value)
    {
        $this->load();
        $this->settings[$id] = $value;
    }
    /**
     * Stores the settings to the database.
     */
    public function persist()
    {
        return update_option(self::KEY, $this->settings);
    }
    /**
     * Loads the settings.
     *
     * @return bool
     */
    private function load(): bool
    {
        if ($this->settings) {
            return \false;
        }
        $this->settings = (array) get_option(self::KEY, array());
        $defaults = array('title' => __('PayPal', 'woocommerce-paypal-payments'), 'description' => __('Pay via PayPal.', 'woocommerce-paypal-payments'), 'smart_button_locations' => $this->default_button_locations, 'smart_button_enable_styling_per_location' => \false, 'pay_later_messaging_enabled' => \true, 'pay_later_button_enabled' => \true, 'pay_later_button_locations' => $this->default_pay_later_button_locations, 'pay_later_messaging_locations' => $this->default_pay_later_messaging_locations, 'brand_name' => get_bloginfo('name'), 'dcc_gateway_title' => $this->default_dcc_gateway_title, 'dcc_gateway_description' => __('Pay with your credit card.', 'woocommerce-paypal-payments'));
        foreach ($defaults as $key => $value) {
            if (isset($this->settings[$key])) {
                $this->settings[$key] = apply_filters('woocommerce_paypal_payments_settings_value', $this->settings[$key], $key);
                continue;
            }
            $this->settings[$key] = $value;
        }
        return \true;
    }
}
