<?php

/**
 * PayPal Commerce Features Definitions
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data\Definition
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Data\Definition;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Service\FeaturesEligibilityService;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
/**
 * Class FeaturesDefinition
 *
 * Provides the definitions for all available features in the system.
 * Each feature has a title, description, eligibility condition, and associated action.
 */
class FeaturesDefinition
{
    /**
     * Save tokenized PayPal and Venmo payment details, required for subscriptions and saving
     * payment methods in user account.
     */
    public const FEATURE_SAVE_PAYPAL_AND_VENMO = 'save_paypal_and_venmo';
    /**
     * Allow to pay in installments.
     */
    public const FEATURE_INSTALLMENTS = 'installments';
    /**
     * Allow customers to buy now and pay later with PayPal
     */
    public const FEATURE_PAY_LATER_MESSAGING = 'pay_later_messaging';
    /**
     * Whether Apple Pay can be used by the merchant. Apple Pay requires an Apple device (like
     * iPhone) to be used by customers.
     */
    public const FEATURE_APPLE_PAY = 'apple_pay';
    /**
     * Merchant eligibility to use Google Pay.
     */
    public const FEATURE_GOOGLE_PAY = 'google_pay';
    /**
     * Advanced card processing eligibility. Required for credit- and debit-card processing.
     */
    public const FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS = 'advanced_credit_and_debit_cards';
    /**
     * Whether alternative payment methods are supported.
     */
    public const FEATURE_ALTERNATIVE_PAYMENT_METHODS = 'alternative_payment_methods';
    /**
     * Contact module allows the merchant to unlock the "Custom Shipping Contact" toggle.
     */
    public const FEATURE_CONTACT_MODULE = 'contact_module';
    /**
     * Whether Pay With Crypto Feature is supported.
     */
    public const FEATURE_PAY_WITH_CRYPTO = 'pwc';
    /**
     * The features eligibility service.
     *
     * @var FeaturesEligibilityService
     */
    protected FeaturesEligibilityService $eligibilities;
    /**
     * The general settings service.
     *
     * @var GeneralSettings
     */
    protected GeneralSettings $settings;
    /**
     * The merchant capabilities.
     *
     * @var array
     */
    protected array $merchant_capabilities;
    /**
     * The plugin settings.
     *
     * @var SettingsModel
     */
    protected SettingsModel $plugin_settings;
    /**
     * Constructor.
     *
     * @param FeaturesEligibilityService $eligibilities The features eligibility service.
     * @param GeneralSettings            $settings The general settings service.
     * @param array                      $merchant_capabilities The merchant capabilities.
     * @param SettingsModel              $plugin_settings The plugin settings.
     */
    public function __construct(FeaturesEligibilityService $eligibilities, GeneralSettings $settings, array $merchant_capabilities, SettingsModel $plugin_settings)
    {
        $this->eligibilities = $eligibilities;
        $this->settings = $settings;
        $this->merchant_capabilities = $merchant_capabilities;
        $this->plugin_settings = $plugin_settings;
    }
    /**
     * Returns the full list of feature definitions with their eligibility conditions.
     *
     * @return array The array of feature definitions.
     */
    public function get(): array
    {
        $all_features = $this->all_available_features();
        $eligible_features = array();
        $eligibility_checks = $this->eligibilities->get_eligibility_checks();
        foreach ($all_features as $feature_key => $feature) {
            if ($eligibility_checks[$feature_key]()) {
                $eligible_features[$feature_key] = $feature;
            }
        }
        return $eligible_features;
    }
    /**
     * Returns all available features.
     *
     * @return array[] The array of all available features.
     */
    public function all_available_features(): array
    {
        $paylater_documentation_supported_countries = array('UK', 'ES', 'IT', 'FR', 'US', 'DE', 'AU');
        $store_country = $this->settings->get_woo_settings()['country'];
        $paylater_docs_country_location = in_array($store_country, $paylater_documentation_supported_countries, \true) ? strtolower($store_country) : 'us';
        $save_paypal_and_venmo = $this->plugin_settings->get_save_paypal_and_venmo();
        $feature_items = array(self::FEATURE_SAVE_PAYPAL_AND_VENMO => array('title' => __('Save PayPal and Venmo', 'woocommerce-paypal-payments'), 'description' => __('Securely save PayPal and Venmo payment methods for subscriptions or return buyers.', 'woocommerce-paypal-payments'), 'enabled' => $this->merchant_capabilities[self::FEATURE_SAVE_PAYPAL_AND_VENMO], 'buttons' => array(array('type' => 'secondary', 'text' => __('Configure', 'woocommerce-paypal-payments'), 'action' => array('type' => 'tab', 'tab' => 'settings', 'section' => 'ppcp-save-paypal-and-venmo'), 'showWhen' => 'enabled', 'class' => 'small-button'), array('type' => 'secondary', 'text' => __('Sign up', 'woocommerce-paypal-payments'), 'urls' => array('sandbox' => 'https://www.sandbox.paypal.com/bizsignup/entry?product=ADVANCED_VAULTING', 'live' => 'https://www.paypal.com/bizsignup/entry?product=ADVANCED_VAULTING'), 'showWhen' => 'disabled', 'class' => 'small-button'), array('type' => 'tertiary', 'text' => __('Learn more', 'woocommerce-paypal-payments'), 'url' => 'https://www.paypal.com/us/enterprise/payment-processing/accept-venmo', 'class' => 'small-button'))), self::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS => array('title' => __('Advanced Credit and Debit Cards', 'woocommerce-paypal-payments'), 'description' => __('Process major credit and debit cards including Visa, Mastercard, American Express and Discover.', 'woocommerce-paypal-payments'), 'enabled' => $this->merchant_capabilities[self::FEATURE_ADVANCED_CREDIT_AND_DEBIT_CARDS], 'buttons' => array(array('type' => 'secondary', 'text' => __('Configure', 'woocommerce-paypal-payments'), 'action' => array('type' => 'tab', 'tab' => 'payment_methods', 'section' => 'ppcp-credit-card-gateway', 'modal' => 'ppcp-credit-card-gateway'), 'showWhen' => 'enabled', 'class' => 'small-button'), array('type' => 'secondary', 'text' => __('Sign up', 'woocommerce-paypal-payments'), 'urls' => array('sandbox' => 'https://www.sandbox.paypal.com/bizsignup/entry?product=ppcp', 'live' => 'https://www.paypal.com/bizsignup/entry?product=ppcp'), 'showWhen' => 'disabled', 'class' => 'small-button'), array('type' => 'tertiary', 'text' => __('Learn more', 'woocommerce-paypal-payments'), 'url' => 'https://developer.paypal.com/studio/checkout/advanced', 'class' => 'small-button'))), self::FEATURE_ALTERNATIVE_PAYMENT_METHODS => array('title' => __('Alternative Payment Methods', 'woocommerce-paypal-payments'), 'description' => __('Offer global, country-specific payment options for your customers.', 'woocommerce-paypal-payments'), 'enabled' => $this->merchant_capabilities[self::FEATURE_ALTERNATIVE_PAYMENT_METHODS], 'buttons' => array(array('type' => 'secondary', 'text' => __('Configure', 'woocommerce-paypal-payments'), 'action' => array('type' => 'tab', 'tab' => 'payment_methods', 'section' => 'ppcp-alternative-payments-card', 'highlight' => \false), 'showWhen' => 'enabled', 'class' => 'small-button'), array('type' => 'secondary', 'text' => __('Sign up', 'woocommerce-paypal-payments'), 'url' => 'https://developer.paypal.com/docs/checkout/apm/', 'showWhen' => 'disabled', 'class' => 'small-button'), array('type' => 'tertiary', 'text' => __('Learn more', 'woocommerce-paypal-payments'), 'url' => 'https://developer.paypal.com/docs/checkout/apm/', 'class' => 'small-button'))), self::FEATURE_GOOGLE_PAY => array('title' => __('Google Pay', 'woocommerce-paypal-payments'), 'description' => __('Let customers pay using their Google Pay wallet.', 'woocommerce-paypal-payments'), 'enabled' => $this->merchant_capabilities[self::FEATURE_GOOGLE_PAY], 'buttons' => array(array('type' => 'secondary', 'text' => __('Configure', 'woocommerce-paypal-payments'), 'action' => array('type' => 'tab', 'tab' => 'payment_methods', 'section' => 'ppcp-googlepay', 'modal' => 'ppcp-googlepay'), 'showWhen' => 'enabled', 'class' => 'small-button'), array('type' => 'secondary', 'text' => __('Sign up', 'woocommerce-paypal-payments'), 'urls' => array('sandbox' => 'https://www.sandbox.paypal.com/bizsignup/add-product?product=payment_methods&capabilities=GOOGLE_PAY', 'live' => 'https://www.paypal.com/bizsignup/add-product?product=payment_methods&capabilities=GOOGLE_PAY'), 'showWhen' => 'disabled', 'class' => 'small-button'), array('type' => 'tertiary', 'text' => __('Learn more', 'woocommerce-paypal-payments'), 'url' => 'https://developer.paypal.com/docs/checkout/apm/google-pay/', 'class' => 'small-button'))), self::FEATURE_APPLE_PAY => array('title' => __('Apple Pay', 'woocommerce-paypal-payments'), 'description' => __('Let customers pay using their Apple Pay wallet.', 'woocommerce-paypal-payments'), 'enabled' => $this->merchant_capabilities[self::FEATURE_APPLE_PAY], 'buttons' => array(array('type' => 'secondary', 'text' => __('Configure', 'woocommerce-paypal-payments'), 'action' => array('type' => 'tab', 'tab' => 'payment_methods', 'section' => 'ppcp-applepay', 'modal' => 'ppcp-applepay'), 'showWhen' => 'enabled', 'class' => 'small-button'), array('type' => 'secondary', 'text' => __('Domain registration', 'woocommerce-paypal-payments'), 'urls' => array('sandbox' => 'https://www.sandbox.paypal.com/uccservicing/apm/applepay', 'live' => 'https://www.paypal.com/uccservicing/apm/applepay'), 'showWhen' => 'enabled', 'class' => 'small-button'), array('type' => 'secondary', 'text' => __('Sign up', 'woocommerce-paypal-payments'), 'urls' => array('sandbox' => 'https://www.sandbox.paypal.com/bizsignup/add-product?product=payment_methods&capabilities=APPLE_PAY', 'live' => 'https://www.paypal.com/bizsignup/add-product?product=payment_methods&capabilities=APPLE_PAY'), 'showWhen' => 'disabled', 'class' => 'small-button'), array('type' => 'tertiary', 'text' => __('Learn more', 'woocommerce-paypal-payments'), 'url' => 'https://developer.paypal.com/docs/checkout/apm/apple-pay/', 'class' => 'small-button'))), self::FEATURE_PAY_LATER_MESSAGING => array('title' => __('Pay Later Messaging', 'woocommerce-paypal-payments'), 'description' => __('Help grow sales with Pay Later messaging. Let customers know they have flexible payment options as they browse, shop, and check out.', 'woocommerce-paypal-payments'), 'enabled' => $this->merchant_capabilities[self::FEATURE_PAY_LATER_MESSAGING] && !$save_paypal_and_venmo, 'buttons' => array(array('type' => 'secondary', 'text' => __('Configure', 'woocommerce-paypal-payments'), 'action' => array('type' => 'tab', 'tab' => 'pay_later_messaging'), 'showWhen' => 'enabled', 'class' => 'small-button'), array('type' => 'tertiary', 'text' => __('Learn more', 'woocommerce-paypal-payments'), 'url' => "https://www.paypal.com/{$paylater_docs_country_location}/business/accept-payments/checkout/installments", 'class' => 'small-button'))), self::FEATURE_INSTALLMENTS => array('title' => __('Installments', 'woocommerce-paypal-payments'), 'description' => __('Allow your customers to pay in installments without interest while you receive the full payment.*', 'woocommerce-paypal-payments') . '<p>' . __('Activate your Installments without interest with PayPal.', 'woocommerce-paypal-payments') . '</p>' . '<p>' . sprintf(
            /* translators: %s: Link to terms and conditions */
            __('*You will receive the full payment minus the applicable PayPal fee. See %s.', 'woocommerce-paypal-payments'),
            '<a href="https://www.paypal.com/mx/webapps/mpp/merchant-fees">' . __('terms and conditions', 'woocommerce-paypal-payments') . '</a>'
        ) . '</p>', 'enabled' => $this->merchant_capabilities[self::FEATURE_INSTALLMENTS], 'buttons' => array(array('type' => 'secondary', 'text' => __('Configure', 'woocommerce-paypal-payments'), 'url' => 'https://www.paypal.com/businessmanage/preferences/installmentplan', 'showWhen' => 'enabled', 'class' => 'small-button'), array('type' => 'secondary', 'text' => __('Sign up', 'woocommerce-paypal-payments'), 'url' => 'https://www.paypal.com/businessmanage/preferences/installmentplan', 'showWhen' => 'disabled', 'class' => 'small-button'))), self::FEATURE_PAY_WITH_CRYPTO => array('title' => __('Pay with Crypto', 'woocommerce-paypal-payments'), 'description' => __('Enable customers to pay with cryptocurrency, and receive payments in USD in your PayPal balance.', 'woocommerce-paypal-payments'), 'enabled' => $this->merchant_capabilities[self::FEATURE_PAY_WITH_CRYPTO], 'buttons' => array(array('type' => 'secondary', 'text' => __('Configure', 'woocommerce-paypal-payments'), 'action' => array('type' => 'tab', 'tab' => 'payment_methods', 'section' => 'ppcp-pay-with-crypto'), 'showWhen' => 'enabled', 'class' => 'small-button'), array('type' => 'secondary', 'text' => __('Sign up', 'woocommerce-paypal-payments'), 'urls' => array('sandbox' => 'https://www.sandbox.paypal.com/bizsignup/add-product?product=CRYPTO_PYMTS', 'live' => 'https://www.paypal.com/bizsignup/add-product?product=CRYPTO_PYMTS'), 'showWhen' => 'disabled', 'class' => 'small-button'), array('type' => 'tertiary', 'text' => __('Learn more', 'woocommerce-paypal-payments'), 'url' => 'https://www.paypal.com/us/digital-wallet/manage-money/crypto', 'class' => 'small-button'))));
        return apply_filters('woocommerce_paypal_payments_features_list', $feature_items);
    }
}
