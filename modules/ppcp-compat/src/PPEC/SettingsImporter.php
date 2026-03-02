<?php

/**
 * PayPal Checkout settings importer.
 *
 * @package WooCommerce\PayPalCommerce\Compat\PPEC
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Compat\PPEC;

use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Data\StylingSettings;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
/**
 * Handles import of settings from PayPal Checkout into PayPal Payments.
 */
class SettingsImporter
{
    private SettingsModel $settings_model;
    private PaymentSettings $payment_settings;
    private StylingSettings $styling_settings;
    private array $ppec_settings;
    /**
     * Context mapping from PPEC to new settings.
     *
     * @var array<string, string>
     */
    private const CONTEXT_MAP = array('' => 'cart', 'single_product' => 'product', 'mini_cart' => 'mini_cart');
    public function __construct(SettingsModel $settings_model, PaymentSettings $payment_settings, StylingSettings $styling_settings)
    {
        $this->settings_model = $settings_model;
        $this->payment_settings = $payment_settings;
        $this->styling_settings = $styling_settings;
        $this->ppec_settings = (array) get_option(\WooCommerce\PayPalCommerce\Compat\PPEC\PPECHelper::PPEC_SETTINGS_OPTION_NAME, array());
    }
    /**
     * Sets up WP hooks to import PayPal Checkout settings into PPCP when needed.
     *
     * @return void
     */
    public function maybe_hook()
    {
        // Import settings the first time the PPCP settings are created.
        if (\WooCommerce\PayPalCommerce\Compat\PPEC\PPECHelper::is_gateway_available() && \false === get_option(SettingsModel::OPTION_KEY)) {
            add_action('add_option_' . SettingsModel::OPTION_KEY, array($this, 'import_settings'), 10, 2);
        }
    }
    /**
     * Updates PayPal Payments settings with values taken from PayPal Checkout settings.
     *
     * @return void
     */
    public function import_settings()
    {
        $this->import_basic_settings();
        $this->import_payment_settings();
        $this->import_styling_settings();
    }
    /**
     * Imports basic settings into SettingsModel.
     *
     * @return void
     */
    private function import_basic_settings(): void
    {
        foreach ($this->ppec_settings as $option_key => $option_value) {
            switch ($option_key) {
                case 'brand_name':
                    $this->settings_model->set_brand_name($option_value);
                    break;
                case 'invoice_prefix':
                    $this->settings_model->set_invoice_prefix($option_value);
                    break;
                case 'landing_page':
                    $landing_page = strtolower($option_value) === 'login' ? 'login' : (strtolower($option_value) === 'billing' ? 'guest_checkout' : 'any');
                    $this->settings_model->set_landing_page($landing_page);
                    break;
                case 'paymentaction':
                    if ('authorization' === $option_value) {
                        $this->settings_model->set_authorize_only(\true);
                    }
                    break;
                case 'instant_payments':
                    $this->settings_model->set_instant_payments_only(wc_string_to_bool($option_value));
                    break;
                case 'debug':
                    $this->settings_model->set_enable_logging(wc_string_to_bool($option_value));
                    break;
                case 'hide_funding_methods':
                    $disabled_cards = array_values(array_intersect(array_map('strtolower', is_array($option_value) ? $option_value : array()), array('card', 'sepa', 'bancontact', 'blik', 'eps', 'giropay', 'ideal', 'mercadopago', 'mybank', 'p24', 'sofort', 'venmo', 'trustly')));
                    $this->settings_model->set_disabled_cards($disabled_cards);
                    break;
            }
        }
        if (isset($this->ppec_settings['credit_enabled']) && 'no' === $this->ppec_settings['credit_enabled']) {
            $disabled_cards = $this->settings_model->get_disabled_cards();
            $disabled_cards = array_merge($disabled_cards, array('credit'));
            $this->settings_model->set_disabled_cards($disabled_cards);
        }
        $this->settings_model->save();
    }
    /**
     * Imports payment gateway settings.
     *
     * @return void
     */
    private function import_payment_settings(): void
    {
        if (isset($this->ppec_settings['title'])) {
            $this->payment_settings->set_method_title(PayPalGateway::ID, $this->ppec_settings['title']);
        }
        if (isset($this->ppec_settings['description'])) {
            $this->payment_settings->set_method_description(PayPalGateway::ID, $this->ppec_settings['description']);
        }
        $this->payment_settings->save();
    }
    /**
     * Imports button styling settings.
     *
     * @return void
     */
    private function import_styling_settings(): void
    {
        $location_styles = array();
        foreach (self::CONTEXT_MAP as $old_context => $new_context) {
            $use_cart_settings = $old_context && (!isset($this->ppec_settings[$old_context . '_settings_toggle']) || 'yes' !== $this->ppec_settings[$old_context . '_settings_toggle']);
            $location_styles[$new_context] = new LocationStylingDTO($new_context, $this->get_button_enabled_state($old_context), $this->enabled_methods($old_context), $this->get_button_property('shape', $old_context, $use_cart_settings) ?: 'rect', $this->get_button_property('label', $old_context, $use_cart_settings) ?: 'paypal', $this->get_button_property('color', $old_context, $use_cart_settings) ?: 'gold', $this->get_button_property('layout', $old_context, $use_cart_settings) ?: 'vertical', \false);
        }
        $this->styling_settings->from_array($location_styles);
        $this->styling_settings->save();
    }
    /**
     * Determines which payment methods are enabled for a specific location.
     *
     * @param string $old_context PPEC context identifier.
     * @return string[] The list of enabled payment method IDs.
     */
    private function enabled_methods(string $old_context): array
    {
        $methods = array();
        if ($this->get_button_enabled_state($old_context)) {
            $methods[] = PayPalGateway::ID;
        }
        $old_prefix = $old_context ? $old_context . '_' : '';
        $use_cart_settings = $old_context && (!isset($this->ppec_settings[$old_context . '_settings_toggle']) || 'yes' !== $this->ppec_settings[$old_context . '_settings_toggle']);
        if ('mini_cart' !== $old_context) {
            $credit_enabled_key = $use_cart_settings ? 'credit_message_enabled' : $old_prefix . 'credit_message_enabled';
            if (!isset($this->ppec_settings[$credit_enabled_key]) || 'yes' === $this->ppec_settings[$credit_enabled_key]) {
                if (!isset($this->ppec_settings['credit_enabled']) || 'yes' === $this->ppec_settings['credit_enabled']) {
                    $methods[] = 'pay-later';
                }
            }
        }
        if (isset($this->ppec_settings['hide_funding_methods']) && is_array($this->ppec_settings['hide_funding_methods'])) {
            if (!in_array('venmo', array_map('strtolower', $this->ppec_settings['hide_funding_methods']), \true)) {
                $methods[] = 'venmo';
            }
        }
        return $methods;
    }
    /**
     * Gets button enabled state for a context.
     *
     * @param string $old_context PPEC context identifier.
     * @return bool
     */
    private function get_button_enabled_state(string $old_context): bool
    {
        switch ($old_context) {
            case 'mini_cart':
            case '':
                return isset($this->ppec_settings['cart_checkout_enabled']) && wc_string_to_bool($this->ppec_settings['cart_checkout_enabled']);
            case 'single_product':
                return isset($this->ppec_settings['checkout_on_single_product_enabled']) && wc_string_to_bool($this->ppec_settings['checkout_on_single_product_enabled']);
            default:
                return \true;
        }
    }
    /**
     * Gets a button property value for a context.
     *
     * @param string $property Property name (layout, label, shape, color).
     * @param string $old_context PPEC context identifier.
     * @param bool   $use_cart_settings Whether to use cart settings as fallback.
     * @return string
     */
    private function get_button_property(string $property, string $old_context, bool $use_cart_settings): string
    {
        $old_prefix = $use_cart_settings || 'color' === $property ? '' : ($old_context ? $old_context . '_' : '');
        $key = $old_prefix . 'button_' . $property;
        return isset($this->ppec_settings[$key]) ? (string) $this->ppec_settings[$key] : '';
    }
}
