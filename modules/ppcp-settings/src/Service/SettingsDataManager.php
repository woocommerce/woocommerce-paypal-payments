<?php

/**
 * Provides functionality for general settings-data management.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

use WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;
use WooCommerce\PayPalCommerce\Settings\DTO\ConfigurationFlagsDTO;
use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Settings\Enum\ProductChoicesEnum;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\Settings\Data\StylingSettings;
use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\PaymentMethodsDefinition;
/**
 * Class SettingsDataManager
 *
 * Manages operations related to plugin settings, primarily focusing on reset functionality.
 * This service can be expanded in the future to include other settings management operations.
 */
class SettingsDataManager
{
    /**
     * The payment methods definition, provides a list of all available payment methods.
     *
     * @var PaymentMethodsDefinition
     */
    private PaymentMethodsDefinition $methods_definition;
    /**
     * The onboarding profile data model.
     *
     * @var OnboardingProfile
     */
    private OnboardingProfile $onboarding_profile;
    /**
     * Payment settings model.
     *
     * @var SettingsModel
     */
    private SettingsModel $payment_settings;
    /**
     * Data model that handles button styling on the front end.
     *
     * @var StylingSettings
     */
    private StylingSettings $styling_settings;
    /**
     * Model for handling payment methods.
     *
     * @var PaymentSettings
     */
    private PaymentSettings $payment_methods;
    /**
     * Data accessors for pay later messaging settings.
     *
     * @var array
     * @todo This should be a proper class!
     */
    private array $paylater_messaging;
    /**
     * Stores a list of all AbstractDataModel instances that are managed by
     * this service.
     *
     * @var AbstractDataModel[]
     */
    private array $models_to_reset = array();
    /**
     * Constructor.
     *
     * @param PaymentMethodsDefinition $methods_definition Access list of all payment methods.
     * @param OnboardingProfile        $onboarding_profile The onboarding profile model.
     * @param GeneralSettings          $general_settings   The general settings model.
     * @param SettingsModel            $payment_settings   The settings model.
     * @param StylingSettings          $styling_settings   The styling settings model.
     * @param PaymentSettings          $payment_methods    The payment settings model.
     * @param array                    $paylater_messaging Paylater Messaging accessor.
     * @param array                    ...$data_models     List of additional data models to reset.
     */
    public function __construct(
        PaymentMethodsDefinition $methods_definition,
        OnboardingProfile $onboarding_profile,
        GeneralSettings $general_settings,
        SettingsModel $payment_settings,
        StylingSettings $styling_settings,
        PaymentSettings $payment_methods,
        array $paylater_messaging,
        // TODO should be migrated to an AbstractDataModel.
        ...$data_models
    )
    {
        foreach ($data_models as $data_model) {
            /**
             * An instance extracted from the spread operator. We only process
             * AbstractDataModel instances.
             *
             * @var mixed|AbstractDataModel $data_model
             */
            if ($data_model instanceof AbstractDataModel) {
                $this->models_to_reset[] = $data_model;
            }
        }
        $this->models_to_reset[] = $onboarding_profile;
        $this->models_to_reset[] = $general_settings;
        $this->models_to_reset[] = $payment_settings;
        $this->models_to_reset[] = $styling_settings;
        $this->models_to_reset[] = $payment_methods;
        $this->methods_definition = $methods_definition;
        $this->onboarding_profile = $onboarding_profile;
        $this->payment_settings = $payment_settings;
        $this->styling_settings = $styling_settings;
        $this->payment_methods = $payment_methods;
        $this->paylater_messaging = $paylater_messaging;
    }
    /**
     * Completely purges all settings from the DB.
     *
     * @return void
     */
    public function reset_all_settings(): void
    {
        /**
         * Broadcast the settings-reset event to allow other modules to perform
         * cleanup tasks, if needed.
         */
        do_action('woocommerce_paypal_payments_reset_settings');
        foreach ($this->models_to_reset as $model) {
            $model->purge();
        }
        // Clear any caches.
        wp_cache_flush();
    }
    /**
     * Applies a default configuration to the plugin for a new merchant.
     *
     * This method checks the onboarding "setup_done" flag to determine if
     * the defaults should be applied. At the end of this method, the
     * "setup_done" flag is set, so future calls to the method have no effect.
     *
     * @param ConfigurationFlagsDTO $flags The configuration flags.
     * @return void
     */
    public function set_defaults_for_new_merchant(ConfigurationFlagsDTO $flags): void
    {
        if ($this->onboarding_profile->is_setup_done()) {
            return;
        }
        $this->apply_configuration($flags);
        $this->onboarding_profile->set_setup_done(\true);
        $this->onboarding_profile->save();
        /**
         * Fires after the core merchant configuration was applied.
         *
         * This action indicates that a merchant completed the onboarding wizard.
         * The flags contain several choices which the merchant took during the
         * onboarding wizard, and provide additional context on which defaults
         * should be applied for the new merchant.
         *
         * Other modules or integrations can use this hook to initialize
         * additional plugin settings on first merchant login.
         */
        do_action('woocommerce_paypal_payments_apply_default_configuration', $flags);
    }
    /**
     * Applies a default configuration to the plugin, without any condition.
     *
     * @param ConfigurationFlagsDTO $flags The configuration flags.
     * @return void
     */
    protected function apply_configuration(ConfigurationFlagsDTO $flags): void
    {
        // Apply defaults for the "Settings" tab.
        $this->apply_payment_settings($flags);
        // Assign defaults for the "Styling" tab.
        $this->apply_location_styles($flags);
        // Assign defaults for the "Pay Later Messaging" tab.
        $this->apply_pay_later_messaging($flags);
    }
    /**
     * Synchronize gateway settings with merchant onboarding choices.
     *
     * @return void
     */
    public function sync_gateway_settings(): void
    {
        $flags = new ConfigurationFlagsDTO();
        $profile_data = $this->onboarding_profile->to_array();
        $flags->is_business_seller = !($profile_data['is_casual_seller'] ?? \false);
        $flags->use_card_payments = $profile_data['accept_card_payments'] ?? \false;
        $flags->use_subscriptions = in_array(ProductChoicesEnum::SUBSCRIPTIONS, $profile_data['products'] ?? array(), \true);
        $this->toggle_payment_gateways($flags);
    }
    /**
     * Enables or disables payment gateways depending on the provided
     * configuration flags.
     *
     * @param ConfigurationFlagsDTO $flags Shop configuration flags.
     * @return void
     */
    protected function toggle_payment_gateways(ConfigurationFlagsDTO $flags): void
    {
        // First, disable all payment methods.
        $methods_paypal = $this->methods_definition->group_paypal_methods();
        $methods_cards = $this->methods_definition->group_card_methods();
        $methods_apm = $this->methods_definition->group_apms();
        $all_methods = array_merge($methods_paypal, $methods_cards, $methods_apm);
        // Enable the Fastlane watermark by default.
        $this->payment_methods->set_fastlane_display_watermark(\true);
        foreach ($all_methods as $method) {
            $this->payment_methods->toggle_method_state($method['id'], \false);
        }
        // Always enable PayPal, Venmo and Pay Later.
        $this->payment_methods->toggle_method_state(PayPalGateway::ID, \true);
        $this->payment_methods->toggle_method_state('venmo', \true);
        if (!$flags->is_business_seller && $flags->use_card_payments) {
            // Use BCDC for casual sellers.
            $this->payment_methods->toggle_method_state(CardButtonGateway::ID, \true);
        }
        if ($flags->is_business_seller) {
            if ($flags->use_card_payments) {
                // Enable ACDC for business sellers.
                $this->payment_methods->toggle_method_state(CreditCardGateway::ID, \true);
                // Apple Pay and Google Pay depend on the ACDC gateway.
                $this->payment_methods->toggle_method_state(ApplePayGateway::ID, \true);
                $this->payment_methods->toggle_method_state(GooglePayGateway::ID, \true);
                // Enable Pay Later for business sellers if subscriptions were not selected.
                // Selecting subscriptions automatically enables the "Save PayPal and Venmo" option, which is incompatible with Pay Later.
                if (!$flags->use_subscriptions) {
                    $this->payment_methods->toggle_method_state('pay-later', \true);
                }
                // Enable BCDC for business sellers without ACDC.
                $this->payment_methods->toggle_method_state(CardButtonGateway::ID, \true);
            }
            /**
             * Allow plugins to modify apm payment gateway states before saving.
             *
             * @param PaymentSettings $payment_methods The payment methods object.
             * @param PaymentSettings $methods_apm List of APM methods.
             * @param ConfigurationFlagsDTO $flags Configuration flags that determine which gateways to enable.
             */
            do_action('woocommerce_paypal_payments_toggle_payment_gateways_apms', $this->payment_methods, $methods_apm, $flags);
        }
        /**
         * Allow plugins to modify payment gateway states before saving.
         *
         * @param PaymentSettings $payment_methods The payment methods object.
         * @param ConfigurationFlagsDTO $flags Configuration flags that determine which gateways to enable.
         */
        do_action('woocommerce_paypal_payments_toggle_payment_gateways', $this->payment_methods, $flags);
        $this->payment_methods->save();
    }
    /**
     * Applies the default payment settings that are relevant for the provided
     * configuration flags.
     *
     * @param ConfigurationFlagsDTO $flags Shop configuration flags.
     * @return void
     */
    protected function apply_payment_settings(ConfigurationFlagsDTO $flags): void
    {
        // Enable Pay-Now experience for all merchants.
        $this->payment_settings->set_enable_pay_now(\true);
        if ($flags->is_business_seller && $flags->use_subscriptions) {
            $this->payment_settings->set_save_paypal_and_venmo(\true);
            $this->payment_settings->set_save_card_details(\true);
        }
        $this->payment_settings->save();
    }
    /**
     * Applies the default styling details for the shop.
     *
     * @param ConfigurationFlagsDTO $flags Shop configuration flags.
     * @return void
     */
    protected function apply_location_styles(ConfigurationFlagsDTO $flags): void
    {
        $methods_full = array(PayPalGateway::ID, 'venmo', 'pay-later', ApplePayGateway::ID, GooglePayGateway::ID);
        $methods_own = array(PayPalGateway::ID, 'venmo', 'pay-later');
        /**
         * Initialize the styling options using the defaults.
         *
         * - Cart: Enabled, display PayPal, Venmo, Pay Later, Google Pay, Apple Pay.
         * - Classic Checkout: Display PayPal, Venmo, Pay Later, Google Pay, Apple Pay.
         * - Express Checkout: Display PayPal, Venmo, Pay Later, Google Pay, Apple Pay.
         * - Mini Cart: Display PayPal, Venmo, Pay Later, Google Pay, Apple Pay.
         * - Product Page: Display PayPal, Venmo, Pay Later.
         */
        $location_styles = array('cart' => new LocationStylingDTO('cart', \true, $methods_full), 'classic_checkout' => new LocationStylingDTO('classic_checkout', \true, $methods_full), 'express_checkout' => new LocationStylingDTO('express_checkout', \true, $methods_full), 'mini_cart' => new LocationStylingDTO('mini_cart', \false, $methods_full), 'product' => new LocationStylingDTO('product', \true, $methods_own));
        // Apply the settings and persist them to the DB. All merchants use the same options.
        $this->styling_settings->from_array($location_styles);
        $this->styling_settings->save();
    }
    /**
     * Applies the default pay later messaging details for the shop.
     *
     * @param ConfigurationFlagsDTO $flags Shop configuration flags.
     * @return void
     */
    protected function apply_pay_later_messaging(ConfigurationFlagsDTO $flags): void
    {
        $config = $this->paylater_messaging['read'];
        $config['cart']['status'] = 'enabled';
        $config['checkout']['status'] = 'enabled';
        $config['product']['status'] = 'enabled';
        $config['shop']['status'] = 'disabled';
        $config['home']['status'] = 'disabled';
        foreach ($config['custom_placement'] as $key => $placement) {
            $config['custom_placement'][$key]['status'] = 'disabled';
        }
        $this->paylater_messaging['save']->save_config($config);
    }
}
