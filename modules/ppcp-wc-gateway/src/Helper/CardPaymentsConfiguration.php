<?php

/**
 * Encapsulates all configuration details for "Credit & Debit Card" gateway.
 *
 * The DCC gateway is also referred to as ACDC or "Advanced Card Processing".
 * When active, we load the newer "card-fields" SDK component, instead of the
 * old "hosted-fields" component.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WooCommerce\PayPalCommerce\Settings\Data\GeneralSettings;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\Axo\Helper\PropertiesDictionary;
use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
/**
 * A configuration proxy service that provides details about credit card gateway
 * configuration.
 *
 * Terminology:
 * - DCC or ACDC are synonymous referring to the "expanded integration"
 *       The credit card form is embedded inline on the checkout page.
 * - BCDC is the "Branded" card payment integration (branded button that opens a modal)
 * - AXO is Fastlane, which is an improved UI for ACDC.
 *
 * Technical implementation via the JS SDK:
 *
 * a. Funding source
 *   - When the funding-source "card" controls the black "Debit or Credit Cards" button:
 *     It's hidden when the funding-source is disabled, and displayed otherwise.
 *     See implementation in class `DisabledFundingSources`.
 *
 * b. Components
 *   - "card-fields" is used by ACDC and AXO.
 *   - The component "hosted-fields" is mentioned in the code, but unclear where/when it's used.
 *
 * DI service: 'wcgateway.configuration.card-configuration'
 */
class CardPaymentsConfiguration
{
    /**
     * The connection state.
     *
     * @var ConnectionState
     */
    private \WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState $connection_state;
    /**
     * The plugin settings instance.
     *
     * @var Settings
     */
    private Settings $settings;
    /**
     * Helper to determine availability of DCC features.
     *
     * @var DccApplies
     */
    private DccApplies $dcc_applies;
    /**
     * Manages the Seller status.
     *
     * @var DCCProductStatus
     */
    private \WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus $dcc_status;
    /**
     * Store country.
     *
     * @var string
     */
    private string $store_country;
    /**
     * This classes lazily resolves settings on first access. This flag indicates
     * whether the setting values were resolved, or still need to be evaluated.
     *
     * @var bool
     */
    public bool $is_resolved = \false;
    /**
     * Indicates whether the merchant uses ACDC (true) or BCDC (false).
     *
     * @var bool
     */
    private bool $use_acdc = \false;
    /**
     * Whether the Credit Card gateway is enabled.
     *
     * @var bool
     */
    private bool $is_enabled = \false;
    /**
     * Whether to use the Fastlane UI.
     *
     * @var bool
     */
    private bool $use_fastlane = \false;
    /**
     * Gateway title.
     *
     * @var string
     */
    private string $gateway_title = '';
    /**
     * Gateway description.
     *
     * @var string
     */
    private string $gateway_description = '';
    /**
     * Whether to display the cardholder's name on the payment form.
     *
     * @var string
     */
    private string $show_name_on_card = 'no';
    /**
     * Whether the Fastlane watermark should be hidden on the front-end.
     *
     * @var bool
     */
    private bool $hide_fastlane_watermark = \false;
    /**
     * Initializes the gateway details based on the provided Settings instance.
     *
     * @param ConnectionState  $connection_state Connection state instance.
     * @param Settings         $settings         Plugin settings instance.
     * @param DccApplies       $dcc_applies      DCC eligibility helper.
     * @param DCCProductStatus $dcc_status       Manages the Seller status.
     * @param string           $store_country    The shop's country code.
     */
    public function __construct(\WooCommerce\PayPalCommerce\WcGateway\Helper\ConnectionState $connection_state, Settings $settings, DccApplies $dcc_applies, \WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus $dcc_status, string $store_country)
    {
        $this->connection_state = $connection_state;
        $this->settings = $settings;
        $this->dcc_applies = $dcc_applies;
        $this->dcc_status = $dcc_status;
        $this->store_country = $store_country;
        $this->is_resolved = \false;
    }
    /**
     * Marks the current settings as "outdated". The next time a setting is accessed
     * it will be resolved using the current settings.
     *
     * @return void
     */
    public function refresh(): void
    {
        $this->is_resolved = \false;
    }
    /**
     * Ensures the internally cached flags correctly reflect the current settings.
     *
     * @return void
     */
    private function ensure_resolved_values(): void
    {
        if ($this->is_resolved) {
            return;
        }
        $this->resolve();
        $this->is_resolved = \true;
    }
    /**
     * Refreshes the internal gateway configuration based on the current settings.
     */
    private function resolve(): void
    {
        $show_on_card_options = array_keys(PropertiesDictionary::cardholder_name_options());
        $show_on_card_value = null;
        // Reset all flags, disable everything.
        $this->use_acdc = \false;
        $this->is_enabled = \false;
        $this->use_fastlane = \false;
        $this->gateway_title = '';
        $this->gateway_description = '';
        $this->show_name_on_card = $show_on_card_options[0];
        // 'no'.
        $this->hide_fastlane_watermark = \false;
        /**
         * Allow modules or other plugins to disable card payments for this shop.
         */
        $disable_card_payments = apply_filters('woocommerce_paypal_payments_card_payments_disabled', \false);
        if ($disable_card_payments) {
            return;
        }
        if (!$this->connection_state->is_connected()) {
            return;
        }
        try {
            $is_paypal_enabled = filter_var($this->settings->get('enabled'), \FILTER_VALIDATE_BOOLEAN);
            // When the core payment logic of the plugin is disabled, we cannot handle card payments.
            if (!$is_paypal_enabled) {
                return;
            }
            $is_dcc_enabled = filter_var($this->settings->get('dcc_enabled'), \FILTER_VALIDATE_BOOLEAN);
            $this->use_fastlane = filter_var($this->settings->get('axo_enabled'), \FILTER_VALIDATE_BOOLEAN);
            if ($this->settings->has('dcc_gateway_title')) {
                $this->gateway_title = $this->settings->get('dcc_gateway_title');
            }
            if ($this->settings->has('dcc_gateway_description')) {
                $this->gateway_description = $this->settings->get('dcc_gateway_description');
            }
            if ($this->settings->has('dcc_name_on_card')) {
                $show_on_card_value = $this->settings->get('dcc_name_on_card');
            } elseif ($this->settings->has('axo_name_on_card')) {
                // Legacy. The AXO gateway setting was replaced by the DCC setting.
                // Remove this condition with the #legacy-ui.
                $show_on_card_value = $this->settings->get('axo_name_on_card') ? 'yes' : 'no';
            }
            if (in_array($show_on_card_value, $show_on_card_options, \true)) {
                $this->show_name_on_card = $show_on_card_value;
            }
        } catch (NotFoundException $exception) {
            // A setting is missing in the DB, disable card payments.
            return;
        }
        /**
         * Filters the "Card Payments Enabled" status. This allows other modules
         * to override the flag.
         */
        $this->is_enabled = (bool) apply_filters('woocommerce_paypal_payments_is_card_payment_enabled', $is_dcc_enabled);
        /**
         * Filters the "ACDC" state. When a filter callback sets this to false
         * the plugin assumes to be in BCDC mode.
         */
        $this->use_acdc = (bool) apply_filters('woocommerce_paypal_payments_is_acdc_active', $this->dcc_applies->for_country_currency() && $this->dcc_status->is_active());
        /**
         * Changing this to true (and hiding the watermark) has potential legal
         * consequences, and therefore is generally discouraged.
         *
         * @since 2024-09-26 - replace the UI checkbox "axo_privacy" with a filter.
         */
        $this->hide_fastlane_watermark = add_filter('woocommerce_paypal_payments_fastlane_watermark_enabled', '__return_false');
    }
    /**
     * Indicated whether the merchant is in ACDC mode.
     *
     * @return bool
     */
    public function use_acdc(): bool
    {
        $this->ensure_resolved_values();
        return $this->use_acdc;
    }
    /**
     * Whether card payments are enabled.
     *
     * Requires PayPal features to be enabled.
     *
     * @internal Use "is_acdc_enabled()" or "is_bcdc_enabled()" instead.
     * @return bool
     */
    public function is_enabled(): bool
    {
        $this->ensure_resolved_values();
        return $this->is_enabled;
    }
    /**
     * True, if the card payments are enabled and the merchant is in ACDC mode.
     * This also unlocks card payments on block pages.
     *
     * If this returns false, the following payment methods are unavailable:
     * - Advanced Card Processing
     * - Fastlane
     * - Google Pay
     * - Apple Pay
     *
     * @return bool
     */
    public function is_acdc_enabled(): bool
    {
        return $this->is_enabled() && $this->use_acdc();
    }
    /**
     * True, if card payments are enabled and the merchant is in BCDC mode.
     *
     * The BCDC integration is not supported by block checkout:
     * When this returns true, disable card payments on block pages.
     *
     * @return bool
     */
    public function is_bcdc_enabled(): bool
    {
        if ('MX' === $this->store_country || !$this->use_acdc()) {
            $bcdc_setting = get_option('woocommerce_ppcp-card-button-gateway_settings');
            $enabled = $bcdc_setting['enabled'] ?? '';
            return 'yes' === $enabled;
        }
        return $this->is_enabled() && !$this->use_acdc();
    }
    /**
     * Whether to prefer Fastlane instead of the default Credit Card UI, if
     * available in the shop's region.
     *
     * Requires PayPal features and the "Advanced Card Payments" gateway to be enabled.
     *
     * @return bool
     */
    public function use_fastlane(): bool
    {
        return $this->is_acdc_enabled() && $this->use_fastlane;
    }
    /**
     * User facing title of the gateway.
     *
     * @param string $fallback Fallback title if the gateway title is not set.
     *
     * @return string Display title of the gateway.
     */
    public function gateway_title(string $fallback = ''): string
    {
        $this->ensure_resolved_values();
        if ($this->gateway_title) {
            return $this->gateway_title;
        }
        return $fallback ?: __('Advanced Card Processing', 'woocommerce-paypal-payments');
    }
    /**
     * Descriptive text to display on the frontend.
     *
     * @param string $fallback Fallback description if the gateway description is not set.
     *
     * @return string Display description of the gateway.
     */
    public function gateway_description(string $fallback = ''): string
    {
        $this->ensure_resolved_values();
        if ($this->gateway_description) {
            return $this->gateway_description;
        }
        return $fallback ?: __('Accept debit and credit cards, and local payment methods with PayPal’s latest solution.', 'woocommerce-paypal-payments');
    }
    /**
     * Whether to show a field for the cardholder's name in the payment form.
     *
     * Note, that this getter returns a string (not a boolean) because the
     * setting is integrated as a select-list, not a toggle or checkbox.
     *
     * @return string ['yes'|'no']
     */
    public function show_name_on_card(): string
    {
        $this->ensure_resolved_values();
        return $this->show_name_on_card;
    }
    /**
     * Whether to display the watermark (text branding) for the Fastlane payment
     * method in the front end.
     *
     * Note: This setting is planned but not implemented yet.
     *
     * @return bool True means, the default watermark is displayed to customers.
     */
    public function show_fastlane_watermark(): bool
    {
        $this->ensure_resolved_values();
        return !$this->hide_fastlane_watermark;
    }
}
