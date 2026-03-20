<?php

/**
 * Payment methods settings class
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Data;

use WC_Payment_Gateway;
/**
 * Class PaymentSettings
 */
class PaymentSettings extends \WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel
{
    /**
     * Option key where profile details are stored.
     *
     * @var string
     */
    protected const OPTION_KEY = 'woocommerce-ppcp-data-payment';
    /**
     * List of WC_Payment_Gateway instances that need to be saved.
     *
     * @var WC_Payment_Gateway[]
     */
    private array $unsaved_gateways = array();
    /**
     * Get default values for the model.
     *
     * @return array
     */
    protected function get_defaults(): array
    {
        return array('paypal_show_logo' => \false, 'cardholder_name' => \false, 'fastlane_display_watermark' => \false, 'venmo_enabled' => \false, 'paylater_enabled' => \false, 'applepay_validated' => \false, 'capture_on_status_change' => \true, 'applepay_checkout_data_mode' => 'use_wc', 'pui_brand_name' => '', 'pui_logo_url' => '', 'pui_customer_service_instructions' => '');
    }
    /**
     * Saves the model data to WordPress options.
     */
    public function save(): void
    {
        parent::save();
        foreach ($this->unsaved_gateways as $gateway) {
            $gateway->settings['enabled'] = $gateway->enabled;
            $gateway->settings['title'] = $gateway->title;
            $gateway->settings['description'] = $gateway->description;
            update_option($gateway->get_option_key(), $gateway->settings);
        }
        $this->unsaved_gateways = array();
    }
    /**
     * Enables or disables the defined payment method, if it exists.
     *
     * @param string $method_id  ID of the payment method.
     * @param bool   $is_enabled Whether to enable the method.
     */
    public function toggle_method_state(string $method_id, bool $is_enabled): void
    {
        switch ($method_id) {
            case 'venmo':
                $this->set_venmo_enabled($is_enabled);
                break;
            case 'pay-later':
                $this->set_paylater_enabled($is_enabled);
                break;
            default:
                $gateway = $this->get_gateway($method_id);
                if ($gateway) {
                    $gateway->enabled = wc_bool_to_string($is_enabled);
                    $this->modified_gateway($gateway);
                } else {
                    $option_key = 'woocommerce_' . $method_id . '_settings';
                    $settings = get_option($option_key, array());
                    $settings['enabled'] = wc_bool_to_string($is_enabled);
                    update_option($option_key, $settings);
                }
        }
    }
    /**
     * Checks, if the provided payment method is enabled.
     *
     * @param string $method_id ID of the payment method.
     * @return bool True, if the method is enabled. False if it's disabled or not existing.
     */
    public function is_method_enabled(string $method_id): bool
    {
        switch ($method_id) {
            case 'venmo':
                return $this->get_venmo_enabled();
            case 'pay-later':
                return $this->get_paylater_enabled();
            default:
                $option_key = 'woocommerce_' . $method_id . '_settings';
                $settings = get_option($option_key, array());
                if (!empty($settings) && isset($settings['enabled'])) {
                    return wc_string_to_bool($settings['enabled']);
                }
                return \false;
        }
    }
    /**
     * Updates the payment method title.
     */
    public function set_method_title(string $method_id, string $title): void
    {
        $gateway = $this->get_gateway($method_id);
        if ($gateway) {
            $gateway->title = $title;
            $this->modified_gateway($gateway);
        } else {
            $option_key = 'woocommerce_' . $method_id . '_settings';
            $settings = get_option($option_key, array());
            $settings['title'] = $title;
            update_option($option_key, $settings);
        }
    }
    /**
     * Updates the payment method description.
     */
    public function set_method_description(string $method_id, string $description): void
    {
        $gateway = $this->get_gateway($method_id);
        if ($gateway) {
            $gateway->description = $description;
            $this->modified_gateway($gateway);
        } else {
            $option_key = 'woocommerce_' . $method_id . '_settings';
            $settings = get_option($option_key, array());
            $settings['description'] = $description;
            update_option($option_key, $settings);
        }
    }
    /**
     * Gets the payment method title.
     *
     * @param string $method_id     ID of the payment method.
     * @param string $default_title Default title to return if method not found.
     * @return string The method title, or an empty string if not found.
     */
    public function get_method_title(string $method_id, string $default_title = ''): string
    {
        $option_key = 'woocommerce_' . $method_id . '_settings';
        $settings = get_option($option_key, array());
        if (!empty($settings) && isset($settings['title'])) {
            return (string) $settings['title'];
        }
        return $default_title;
    }
    /**
     * Gets the payment method description.
     *
     * @param string $method_id           ID of the payment method.
     * @param string $default_description Default description to return if method not found.
     * @return string The method description, or an empty string if not found.
     */
    public function get_method_description(string $method_id, string $default_description = ''): string
    {
        $option_key = 'woocommerce_' . $method_id . '_settings';
        $settings = get_option($option_key, array());
        if (!empty($settings) && isset($settings['description'])) {
            return (string) $settings['description'];
        }
        return $default_description;
    }
    /**
     * Whether to display the PayPal logo on the checkout page as an additional trust sign.
     */
    public function get_paypal_show_logo(): bool
    {
        return (bool) $this->data['paypal_show_logo'];
    }
    /**
     * Whether to ask for the card-holder name during checkout.
     * If true, a new field is displayed during checkout when paying with CC.
     */
    public function get_cardholder_name(): bool
    {
        return (bool) $this->data['cardholder_name'];
    }
    /**
     * Get Fastlane display watermark.
     */
    public function get_fastlane_display_watermark(): bool
    {
        return (bool) $this->data['fastlane_display_watermark'];
    }
    /**
     * Get Venmo enabled.
     */
    public function get_venmo_enabled(): bool
    {
        return (bool) $this->data['venmo_enabled'];
    }
    /**
     * Get Pay Later enabled.
     */
    public function get_paylater_enabled(): bool
    {
        return (bool) $this->data['paylater_enabled'];
    }
    /**
     * @see self::get_paypal_show_logo()
     */
    public function set_paypal_show_logo(bool $value): void
    {
        $this->data['paypal_show_logo'] = $value;
    }
    /**
     * @see self::get_cardholder_name()
     */
    public function set_cardholder_name(bool $value): void
    {
        $this->data['cardholder_name'] = $value;
    }
    /**
     * Set Fastlane display watermark.
     */
    public function set_fastlane_display_watermark(bool $value): void
    {
        $this->data['fastlane_display_watermark'] = $value;
    }
    /**
     * Set Venmo enabled.
     */
    public function set_venmo_enabled(bool $value): void
    {
        $this->data['venmo_enabled'] = $value;
    }
    /**
     * Set Pay Later enabled.
     */
    public function set_paylater_enabled(bool $value): void
    {
        $this->data['paylater_enabled'] = $value;
    }
    /**
     * Get the gateway object for the given method ID.
     */
    private function get_gateway(string $method_id): ?WC_Payment_Gateway
    {
        if (isset($this->unsaved_gateways[$method_id])) {
            return $this->unsaved_gateways[$method_id];
        }
        $gateways = WC()->payment_gateways()->payment_gateways();
        if (!isset($gateways[$method_id])) {
            return null;
        }
        $gateway = $gateways[$method_id];
        $gateway->init_form_fields();
        return $gateway;
    }
    /**
     * Store the gateway object for later saving.
     */
    private function modified_gateway(WC_Payment_Gateway $gateway): void
    {
        $this->unsaved_gateways[$gateway->id] = $gateway;
    }
    /**
     * Whether the domain verification for ApplePay completed successfully.
     */
    public function get_applepay_validated(): bool
    {
        return (bool) $this->data['applepay_validated'];
    }
    /**
     * @see self::get_applepay_validated()
     */
    public function set_applepay_validated(bool $value): void
    {
        $this->data['applepay_validated'] = $value;
    }
    /**
     * Get Apple Pay checkout data mode.
     */
    public function get_applepay_checkout_data_mode(): string
    {
        return (string) $this->data['applepay_checkout_data_mode'];
    }
    /**
     * Todo: This setter is not used anywhere/no UI option.
     *
     * @see self::get_applepay_checkout_data_mode()
     */
    public function set_applepay_checkout_data_mode(string $value): void
    {
        $this->data['applepay_checkout_data_mode'] = $value;
    }
    /**
     * Get Pay upon Invoice brand name.
     */
    public function get_pui_brand_name(): string
    {
        return (string) $this->data['pui_brand_name'];
    }
    /**
     * Set Pay upon Invoice brand name.
     */
    public function set_pui_brand_name(string $value): void
    {
        $this->data['pui_brand_name'] = $value;
    }
    /**
     * Get Pay upon Invoice logo URL.
     */
    public function get_pui_logo_url(): string
    {
        return (string) $this->data['pui_logo_url'];
    }
    /**
     * Set Pay upon Invoice logo URL.
     */
    public function set_pui_logo_url(string $value): void
    {
        $this->data['pui_logo_url'] = $value;
    }
    /**
     * Get Pay upon Invoice customer service instructions.
     */
    public function get_pui_customer_service_instructions(): string
    {
        return (string) $this->data['pui_customer_service_instructions'];
    }
    /**
     * Set Pay upon Invoice customer service instructions.
     */
    public function set_pui_customer_service_instructions(string $value): void
    {
        $this->data['pui_customer_service_instructions'] = $value;
    }
    /**
     * A legacy setting that has no React UI: Whether to capture an authorize-only payment
     * when the order is transitioned to "Processing". No setter.
     */
    public function get_capture_on_status_change(): bool
    {
        return $this->data['capture_on_status_change'];
    }
}
