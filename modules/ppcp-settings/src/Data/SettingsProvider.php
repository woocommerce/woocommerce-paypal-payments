<?php

/**
 * PayPal Commerce Provider Class
 *
 * The goal of the class is to have all new settings UI classes injected and serve as settings provider from one single place.
 * Modules would use this SettingsProvider class to update the code from using the legacy Settings class to use the new settings.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Data
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Data;

use WooCommerce\PayPalCommerce\Settings\DTO\LocationStylingDTO;
use WooCommerce\PayPalCommerce\Settings\DTO\MerchantConnectionDTO;
class SettingsProvider
{
    private \WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings $general_settings;
    private \WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile $onboarding_profile;
    private \WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings $payment_settings;
    private \WooCommerce\PayPalCommerce\Settings\Data\SettingsModel $settings_model;
    private \WooCommerce\PayPalCommerce\Settings\Data\StylingSettings $styling_settings;
    public function __construct(\WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings $general_settings, \WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile $onboarding_profile, \WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings $payment_settings, \WooCommerce\PayPalCommerce\Settings\Data\SettingsModel $settings_model, \WooCommerce\PayPalCommerce\Settings\Data\StylingSettings $styling_settings)
    {
        $this->general_settings = $general_settings;
        $this->onboarding_profile = $onboarding_profile;
        $this->payment_settings = $payment_settings;
        $this->settings_model = $settings_model;
        $this->styling_settings = $styling_settings;
    }
    /**
     * Gets the 'use sandbox' setting.
     *
     * @return bool
     */
    public function use_sandbox(): bool
    {
        return $this->general_settings->get_sandbox();
    }
    /**
     * Whether the currently connected merchant is a sandbox account.
     *
     * @return bool
     */
    public function sandbox_merchant(): bool
    {
        return $this->general_settings->is_sandbox_merchant();
    }
    /**
     * Whether the merchant uses a business account.
     *
     * Note: It's possible that the seller type is unknown, and both methods,
     * `is_casual_seller()` and `is_business_seller()` return false.
     *
     * @return bool
     */
    public function business_seller(): bool
    {
        return $this->general_settings->is_business_seller();
    }
    /**
     * Whether the merchant is a casual seller using a personal account.
     *
     * Note: It's possible that the seller type is unknown, and both methods,
     * `is_casual_seller()` and `is_business_seller()` return false.
     *
     * @return bool
     */
    public function casual_seller(): bool
    {
        return $this->general_settings->is_casual_seller();
    }
    /**
     * Returns the list of read-only customization flags.
     *
     * @return array
     */
    public function woo_settings(): array
    {
        return $this->general_settings->get_woo_settings();
    }
    /**
     * Returns the full merchant connection DTO for the current connection.
     *
     * @return MerchantConnectionDTO All connection details.
     */
    public function merchant_data(): MerchantConnectionDTO
    {
        return $this->general_settings->get_merchant_data();
    }
    /**
     * Whether the merchant successfully logged into their PayPal account.
     *
     * @return bool
     */
    public function merchant_connected(): bool
    {
        return $this->general_settings->is_merchant_connected();
    }
    /**
     * Gets the currently connected merchant ID.
     *
     * @return string
     */
    public function merchant_id(): string
    {
        return $this->general_settings->get_merchant_id();
    }
    /**
     * Gets the currently connected merchant's email.
     *
     * @return string
     */
    public function merchant_email(): string
    {
        return $this->general_settings->get_merchant_email();
    }
    /**
     * Gets the currently connected merchant's country.
     *
     * @return string
     */
    public function merchant_country(): string
    {
        return $this->general_settings->get_merchant_country();
    }
    /**
     * Whether the plugin is in the branded-experience mode and shows/enables only
     * payment methods that are PayPal's own brand.
     *
     * @return bool
     */
    public function own_brand_only(): bool
    {
        return $this->general_settings->own_brand_only();
    }
    /**
     * Retrieves the installation path. Used for the branded experience.
     *
     * @return string
     */
    public function installation_path(): string
    {
        return $this->general_settings->get_installation_path();
    }
    /**
     * Gets the Onboarding 'completed' flag.
     *
     * @return bool
     */
    public function onboarding_completed(): bool
    {
        return $this->onboarding_profile->get_completed();
    }
    /**
     * Gets the Onboarding 'step' setting.
     *
     * @return int
     */
    public function onboarding_step(): int
    {
        return $this->onboarding_profile->get_step();
    }
    /**
     * Whether the merchant wants to accept card payments via the PayPal plugin.
     *
     * @return bool
     */
    public function accept_card_payments(): bool
    {
        return $this->onboarding_profile->get_accept_card_payments();
    }
    /**
     * Gets the active product types for this store.
     *
     * @return string[] Any of ['virtual'|'physical'|'subscriptions'].
     */
    public function products(): array
    {
        return $this->onboarding_profile->get_products();
    }
    /**
     * Returns the list of read-only customization flags
     *
     * @return array
     */
    public function flags(): array
    {
        return $this->onboarding_profile->get_flags();
    }
    /**
     * Gets the 'setup_done' flag.
     *
     * @return bool
     */
    public function setup_done(): bool
    {
        return $this->onboarding_profile->is_setup_done();
    }
    /**
     * Get whether gateways have been synced.
     *
     * @return bool
     */
    public function gateways_synced(): bool
    {
        return $this->onboarding_profile->is_gateways_synced();
    }
    /**
     * Get whether gateways have been refreshed.
     *
     * @return bool
     */
    public function gateways_refreshed(): bool
    {
        return $this->onboarding_profile->is_gateways_refreshed();
    }
    /**
     * If it should show the PayPal logo.
     *
     * @return bool
     */
    public function show_paypal_logo(): bool
    {
        return $this->payment_settings->get_paypal_show_logo();
    }
    /**
     * If it should show CardHolder name.
     *
     * @return bool
     */
    public function show_cardholder_name(): bool
    {
        return $this->payment_settings->get_cardholder_name();
    }
    /**
     * Get if Fastlane should display watermark.
     *
     * @return bool
     */
    public function show_fastlane_watermark(): bool
    {
        return $this->payment_settings->get_fastlane_display_watermark();
    }
    /**
     * Get if Venmo is enabled.
     *
     * @return bool
     */
    public function venmo_enabled(): bool
    {
        return $this->payment_settings->get_venmo_enabled();
    }
    /**
     * Get if Pay Later is enabled.
     *
     * @return bool
     */
    public function paylater_enabled(): bool
    {
        return $this->payment_settings->get_paylater_enabled();
    }
    /**
     * Gets the invoice prefix.
     *
     * @return string The invoice prefix.
     */
    public function invoice_prefix(): string
    {
        return $this->settings_model->get_invoice_prefix();
    }
    /**
     * Gets the brand name.
     *
     * @return string The brand name.
     */
    public function brand_name(): string
    {
        return $this->settings_model->get_brand_name();
    }
    /**
     * Gets the soft descriptor.
     *
     * @return string The soft descriptor.
     */
    public function soft_descriptor(): string
    {
        return $this->settings_model->get_soft_descriptor();
    }
    /**
     * Gets the subtotal adjustment setting.
     *
     * @return string The subtotal adjustment setting.
     */
    public function subtotal_adjustment(): string
    {
        return $this->settings_model->get_subtotal_adjustment();
    }
    /**
     * Gets the landing page setting.
     *
     * @return string The landing page setting.
     */
    public function landing_page(): string
    {
        return $this->settings_model->get_landing_page();
    }
    /**
     * Gets the button language setting.
     *
     * @return string The button language.
     */
    public function button_language(): string
    {
        return $this->settings_model->get_button_language();
    }
    /**
     * Gets the 3D Secure setting.
     *
     * @return string The 3D Secure setting.
     */
    public function three_d_secure(): string
    {
        return $this->settings_model->get_three_d_secure();
    }
    public function is_payment_level_processing_enabled(): bool
    {
        return $this->settings_model->get_payment_level_processing();
    }
    public function ships_from_postal_code(): string
    {
        return $this->settings_model->get_ships_from_postal_code();
    }
    /**
     * Gets the authorize only setting.
     *
     * @return bool True if authorize only is enabled, false otherwise.
     */
    public function authorize_only(): bool
    {
        return $this->settings_model->get_authorize_only();
    }
    /**
     * Gets the capture virtual orders setting.
     *
     * @return bool True if capturing virtual orders is enabled, false otherwise.
     */
    public function capture_virtual_orders(): bool
    {
        return $this->settings_model->get_capture_virtual_orders();
    }
    /**
     * Gets the save PayPal and Venmo setting.
     *
     * @return bool True if saving PayPal and Venmo is enabled, false otherwise.
     */
    public function save_paypal_and_venmo(): bool
    {
        return $this->settings_model->get_save_paypal_and_venmo();
    }
    /**
     * Gets the instant payments only setting.
     *
     * @return bool True if instant payments only setting is enabled, false otherwise.
     */
    public function instant_payments_only(): bool
    {
        return $this->settings_model->get_instant_payments_only();
    }
    /**
     * Gets the custom-shipping-contact flag ("Contact Module").
     *
     * @return bool True if the contact module feature is enabled, false otherwise.
     */
    public function enable_contact_module(): bool
    {
        return $this->settings_model->get_enable_contact_module();
    }
    /**
     * Gets the save card details setting.
     *
     * @return bool True if saving card details is enabled, false otherwise.
     */
    public function save_card_details(): bool
    {
        return $this->settings_model->get_save_card_details();
    }
    /**
     * Gets the enable Pay Now setting.
     *
     * @return bool True if Pay Now is enabled, false otherwise.
     */
    public function enable_pay_now(): bool
    {
        return $this->settings_model->get_enable_pay_now();
    }
    /**
     * Gets the enable logging setting.
     *
     * @return bool True if logging is enabled, false otherwise.
     */
    public function enable_logging(): bool
    {
        return $this->settings_model->get_enable_logging();
    }
    /**
     * Gets the disabled cards.
     *
     * @return array The array of disabled cards.
     */
    public function disabled_cards(): array
    {
        return $this->settings_model->get_disabled_cards();
    }
    /**
     * Gets the Stay Updated setting.
     *
     * @return bool True if Stay Updated is enabled, false otherwise.
     */
    public function stay_updated(): bool
    {
        return $this->settings_model->get_stay_updated();
    }
    /**
     * Get styling details for Cart and Block Cart.
     *
     * @return LocationStylingDTO
     */
    public function styling_cart(): LocationStylingDTO
    {
        return $this->styling_settings->get_cart();
    }
    /**
     * Get styling details for Classic Checkout.
     *
     * @return LocationStylingDTO
     */
    public function styling_classic_checkout(): LocationStylingDTO
    {
        return $this->styling_settings->get_classic_checkout();
    }
    /**
     * Get styling details for Express Checkout.
     *
     * @return LocationStylingDTO
     */
    public function styling_express_checkout(): LocationStylingDTO
    {
        return $this->styling_settings->get_express_checkout();
    }
    /**
     * Get styling details for Mini Cart
     *
     * @return LocationStylingDTO
     */
    public function styling_mini_cart(): LocationStylingDTO
    {
        return $this->styling_settings->get_mini_cart();
    }
    /**
     * Get styling details for Product Page.
     *
     * @return LocationStylingDTO
     */
    public function styling_product(): LocationStylingDTO
    {
        return $this->styling_settings->get_product();
    }
}
