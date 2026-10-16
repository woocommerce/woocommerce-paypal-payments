<?php

/**
 * The settings object.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
/**
 * Class Settings
 */
class Settings implements ContainerInterface
{
    const KEY = 'woocommerce-ppcp-settings';
    /**
     * The settings.
     *
     * @var array|null
     */
    private ?array $settings = null;
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
     * Settings constructor.
     *
     * @param string[] $default_button_locations              The list of selected default
     *                                                                 button locations.
     * @param string   $default_dcc_gateway_title             The default ACDC gateway
     *                                                                 title.
     * @param string[] $default_pay_later_button_locations    The list of selected default
     *                                                                 pay later button locations.
     * @param string[] $default_pay_later_messaging_locations The list of selected default
     *                                                                 pay later messaging
     *                                                                 locations.
     */
    public function __construct(array $default_button_locations, string $default_dcc_gateway_title, array $default_pay_later_button_locations, array $default_pay_later_messaging_locations)
    {
        $this->default_button_locations = $default_button_locations;
        $this->default_dcc_gateway_title = $default_dcc_gateway_title;
        $this->default_pay_later_button_locations = $default_pay_later_button_locations;
        $this->default_pay_later_messaging_locations = $default_pay_later_messaging_locations;
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
        if (isset($this->settings[$id])) {
            return $this->settings[$id];
        }
        $defaults = $this->get_defaults();
        return $defaults[$id];
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
        $this->load();
        if (isset($this->settings[$id])) {
            return \true;
        }
        return in_array($id, $this->get_default_keys(), \true);
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
     * Loads the settings from the database.
     *
     * @return bool
     */
    private function load(): bool
    {
        if ($this->settings !== null) {
            return \false;
        }
        $this->settings = (array) get_option(self::KEY, array());
        return \true;
    }
    /**
     * Returns the keys of settings that have default values.
     *
     * @return string[]
     */
    private function get_default_keys(): array
    {
        return array('title', 'description', 'smart_button_locations', 'smart_button_enable_styling_per_location', 'pay_later_messaging_enabled', 'pay_later_button_enabled', 'pay_later_button_locations', 'pay_later_messaging_locations', 'brand_name', 'dcc_gateway_title', 'dcc_gateway_description');
    }
    /**
     * Returns the default values for settings.
     *
     * @return array
     */
    private function get_defaults(): array
    {
        return array('title' => __('PayPal', 'woocommerce-paypal-payments'), 'description' => __('Pay via PayPal.', 'woocommerce-paypal-payments'), 'smart_button_locations' => $this->default_button_locations, 'smart_button_enable_styling_per_location' => \false, 'pay_later_messaging_enabled' => \true, 'pay_later_button_enabled' => \true, 'pay_later_button_locations' => $this->default_pay_later_button_locations, 'pay_later_messaging_locations' => $this->default_pay_later_messaging_locations, 'brand_name' => get_bloginfo('name'), 'dcc_gateway_title' => $this->default_dcc_gateway_title, 'dcc_gateway_description' => __('Pay with your credit card.', 'woocommerce-paypal-payments'));
    }
}
