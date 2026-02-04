<?php

/**
 * PayPal Commerce Script Data Handler.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */
namespace WooCommerce\PayPalCommerce\Settings\Service;

use WooCommerce\PayPalCommerce\ApiClient\Helper\PartnerAttribution;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PaymentLevelEligibility;
use WooCommerce\PayPalCommerce\Assets\AssetGetter;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
/**
 * Class ScriptDataHandler
 * This class is responsible for localizing the scripts and styles for the settings page.
 */
class ScriptDataHandler
{
    /**
     * The settings object.
     *
     * @var Settings
     */
    protected Settings $settings;
    private AssetGetter $asset_getter;
    /**
     * Whether the pay later configurator is available.
     *
     * @var bool
     */
    protected bool $paylater_is_available;
    /**
     * The store country.
     *
     * @var string
     */
    protected string $store_country;
    /**
     * The merchant ID.
     *
     * @var string
     */
    protected string $merchant_id;
    /**
     * The button language choices.
     *
     * @var array
     */
    protected array $button_language_choices;
    /**
     * The partner attribution object.
     *
     * @var PartnerAttribution
     */
    protected PartnerAttribution $partner_attribution;
    protected PaymentLevelEligibility $payment_level_eligibility;
    public function __construct(Settings $settings, AssetGetter $asset_getter, bool $paylater_is_available, string $store_country, string $merchant_id, array $button_language_choices, PartnerAttribution $partner_attribution, PaymentLevelEligibility $payment_level_eligibility)
    {
        $this->settings = $settings;
        $this->asset_getter = $asset_getter;
        $this->paylater_is_available = $paylater_is_available;
        $this->store_country = $store_country;
        $this->merchant_id = $merchant_id;
        $this->button_language_choices = $button_language_choices;
        $this->partner_attribution = $partner_attribution;
        $this->payment_level_eligibility = $payment_level_eligibility;
    }
    /**
     * Localize scripts.
     *
     * @param string $hook_suffix The current admin page.
     */
    public function localize_scripts(string $hook_suffix): void
    {
        /**
         * Param types removed to avoid third-party issues.
         *
         * @psalm-suppress MissingClosureParamType
         */
        if ('woocommerce_page_wc-settings' !== $hook_suffix) {
            return;
        }
        /**
         * Require resolves.
         *
         * @psalm-suppress UnresolvableInclude
         */
        $script_asset_file = require $this->asset_getter->get_asset_php_path('index.js');
        wp_register_script('ppcp-admin-settings', $this->asset_getter->get_asset_url('index.js'), $script_asset_file['dependencies'], $script_asset_file['version'], \true);
        wp_enqueue_script('ppcp-admin-settings', '', array('wp-i18n'), $script_asset_file['version'], \true);
        wp_set_script_translations('ppcp-admin-settings', 'woocommerce-paypal-payments');
        /** @psalm-suppress UnresolvableInclude */
        $style_asset_file = require $this->asset_getter->get_asset_php_path('styles.css');
        wp_register_style('ppcp-admin-settings', $this->asset_getter->get_asset_url('styles.css'), $style_asset_file['dependencies'], $style_asset_file['version']);
        wp_enqueue_style('ppcp-admin-settings');
        wp_enqueue_style('ppcp-admin-settings-font', 'https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap', array(), $style_asset_file['version']);
        $is_pay_later_configurator_available = $this->paylater_is_available;
        $disabled_cards_choices = array(array('value' => 'visa', 'label' => _x('Visa', 'Name of credit card', 'woocommerce-paypal-payments')), array('value' => 'mastercard', 'label' => _x('Mastercard', 'Name of credit card', 'woocommerce-paypal-payments')), array('value' => 'amex', 'label' => _x('American Express', 'Name of credit card', 'woocommerce-paypal-payments')), array('value' => 'discover', 'label' => _x('Discover', 'Name of credit card', 'woocommerce-paypal-payments')), array('value' => 'jcb', 'label' => _x('JCB', 'Name of credit card', 'woocommerce-paypal-payments')), array('value' => 'elo', 'label' => _x('Elo', 'Name of credit card', 'woocommerce-paypal-payments')), array('value' => 'hiper', 'label' => _x('Hiper', 'Name of credit card', 'woocommerce-paypal-payments')));
        $three_d_secure_options = array(array('value' => 'no-3d-secure', 'label' => __('No 3D Secure', 'woocommerce-paypal-payments'), 'description' => __('Do not use 3D Secure authentication for any transactions.', 'woocommerce-paypal-payments')), array('value' => 'only-required-3d-secure', 'label' => __('Only when required', 'woocommerce-paypal-payments'), 'description' => __('Use 3D Secure when required by the card issuer or payment processor.', 'woocommerce-paypal-payments')), array('value' => 'always-3d-secure', 'label' => __('Always require 3D Secure', 'woocommerce-paypal-payments'), 'description' => __('Always authenticate transactions with 3D Secure when available.', 'woocommerce-paypal-payments')));
        $transformed_button_choices = array_map(function ($key, $value) {
            return array('value' => $key, 'label' => $value);
        }, array_keys($this->button_language_choices), $this->button_language_choices);
        $script_data = array('assets' => array('imagesUrl' => $this->asset_getter->get_static_asset_url('images/')), 'wcPaymentsTabUrl' => admin_url('admin.php?page=wc-settings&tab=checkout'), 'pluginSettingsUrl' => admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway'), 'debug' => defined('WP_DEBUG') && WP_DEBUG, 'isPayLaterConfiguratorAvailable' => $is_pay_later_configurator_available, 'storeCountry' => $this->store_country, 'storePostcode' => get_option('woocommerce_store_postcode', ''), 'buttonLanguageChoices' => $transformed_button_choices, 'disabledCardsChoices' => $disabled_cards_choices, 'threeDSecureOptions' => $three_d_secure_options, 'isEligibleForPaymentLevelProcessing' => $this->payment_level_eligibility->is_eligible(CreditCardGateway::ID));
        if ($is_pay_later_configurator_available) {
            wp_enqueue_script('ppcp-paylater-configurator-lib', 'https://www.paypalobjects.com/merchant-library/merchant-configurator.js', array('wp-i18n'), $script_asset_file['version'], \true);
            wp_set_script_translations('ppcp-paylater-configurator-lib', 'woocommerce-paypal-payments');
            $script_data['PcpPayLaterConfigurator'] = array('config' => array(), 'merchantClientId' => $this->settings->get('client_id'), 'partnerClientId' => $this->merchant_id, 'bnCode' => $this->partner_attribution->get_bn_code());
        }
        wp_localize_script('ppcp-admin-settings', 'ppcpSettings', $script_data);
        // Dequeue the PayPal Subscription script.
        wp_dequeue_script('ppcp-paypal-subscription');
    }
}
