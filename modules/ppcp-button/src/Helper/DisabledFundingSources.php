<?php

/**
 * Creates the list of disabled funding sources.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcSubscriptions\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CartCheckoutDetector;
/**
 * Class DisabledFundingSources
 */
class DisabledFundingSources
{
    use FreeTrialHandlerTrait;
    /**
     * The settings.
     *
     * @var Settings
     */
    private Settings $settings;
    /**
     * All existing funding sources.
     *
     * @var array
     */
    private array $all_funding_sources;
    /**
     * Provides details about the DCC configuration.
     *
     * @var CardPaymentsConfiguration
     */
    private CardPaymentsConfiguration $dcc_configuration;
    /**
     * Merchant Country
     *
     * @var string
     */
    private string $merchant_country;
    /**
     * DisabledFundingSources constructor.
     *
     * @param Settings                  $settings            The settings.
     * @param array                     $all_funding_sources All existing funding sources.
     * @param CardPaymentsConfiguration $dcc_configuration   DCC gateway configuration.
     * @param string                    $merchant_country    Merchant country.
     */
    public function __construct(Settings $settings, array $all_funding_sources, CardPaymentsConfiguration $dcc_configuration, string $merchant_country)
    {
        $this->settings = $settings;
        $this->all_funding_sources = $all_funding_sources;
        $this->dcc_configuration = $dcc_configuration;
        $this->merchant_country = $merchant_country;
    }
    /**
     * Returns the list of funding sources to be disabled.
     *
     * @param string $context The context.
     * @return string[] List of disabled sources
     */
    public function sources(string $context): array
    {
        $block_contexts = array('checkout-block', 'cart-block');
        $flags = array('context' => $context, 'is_block_context' => in_array($context, $block_contexts, \true), 'is_free_trial' => $this->is_free_trial_cart());
        // Free trials have a shorter, special funding-source rule.
        if ($flags['is_free_trial']) {
            $disable_funding = $this->get_sources_for_free_trial();
            return $this->sanitize_and_filter_sources($disable_funding, $flags);
        }
        $disable_funding = $this->get_sources_from_settings();
        // Apply rules based on context and payment methods.
        $disable_funding = $this->apply_context_rules($disable_funding);
        // Apply special rules for block checkout.
        if ($flags['is_block_context']) {
            $disable_funding = $this->apply_block_checkout_rules($disable_funding);
        }
        return $this->sanitize_and_filter_sources($disable_funding, $flags);
    }
    /**
     * Gets disabled funding sources from settings.
     *
     * @return array
     */
    private function get_sources_from_settings(): array
    {
        try {
            // Settings field present in the legacy UI.
            $disabled_funding = $this->settings->get('disable_funding');
        } catch (NotFoundException $exception) {
            $disabled_funding = array();
        }
        /**
         * Filters the list of disabled funding methods. In the legacy UI, this
         * list was accessible via a settings field.
         *
         * This filter allows merchants to programmatically disable funding sources
         * in the new UI.
         */
        return (array) apply_filters('woocommerce_paypal_payments_disabled_funding', $disabled_funding);
    }
    /**
     * Gets disabled funding sources for free trial carts.
     *
     * Rule: Carts that include a free trial product can ONLY use the
     * funding source "card" - all other sources are disabled.
     *
     * @return array
     */
    private function get_sources_for_free_trial(): array
    {
        // Disable all sources.
        $disable_funding = array_keys($this->all_funding_sources);
        if (is_checkout() && $this->dcc_configuration->is_bcdc_enabled()) {
            // If BCDC is used, re-enable card payments.
            $disable_funding = array_filter($disable_funding, static fn(string $funding_source) => $funding_source !== 'card');
        }
        return $disable_funding;
    }
    /**
     * Applies rules based on context and payment methods.
     *
     * @param array $disable_funding The current disabled funding sources.
     * @return array
     */
    private function apply_context_rules(array $disable_funding): array
    {
        if ('MX' === $this->merchant_country && $this->dcc_configuration->is_bcdc_enabled() && CartCheckoutDetector::has_classic_checkout() && is_checkout()) {
            return $disable_funding;
        }
        if (!is_checkout() || $this->dcc_configuration->use_acdc()) {
            // Non-checkout pages, or ACDC capability: Don't load card button.
            $disable_funding[] = 'card';
        }
        return $disable_funding;
    }
    /**
     * Applies special rules for block checkout.
     *
     * @param array $disable_funding The current disabled funding sources.
     * @return array
     */
    private function apply_block_checkout_rules(array $disable_funding): array
    {
        /**
         * Block checkout only supports the following funding methods:
         * - PayPal
         * - PayLater
         * - Venmo
         * - ACDC ("card", conditionally)
         */
        $allowed_in_blocks = array('venmo', 'paylater', 'paypal', 'card');
        return array_merge($disable_funding, array_diff(array_keys($this->all_funding_sources), $allowed_in_blocks));
    }
    /**
     * Filters the disabled "funding-sources" list and returns a sanitized array.
     *
     * @param array $disable_funding The disabled funding sources.
     * @param array $flags           Decision flags.
     * @return string[]
     */
    private function sanitize_and_filter_sources(array $disable_funding, array $flags): array
    {
        /**
         * Filters the final list of disabled funding sources.
         *
         * @param array $disable_funding The filter value, funding sources to be disabled.
         * @param array $flags           Decision flags to provide more context to filters.
         */
        $disable_funding = apply_filters('woocommerce_paypal_payments_sdk_disabled_funding_hook', $disable_funding, array('context' => (string) ($flags['context'] ?? ''), 'is_block_context' => (bool) ($flags['is_block_context'] ?? \false), 'is_free_trial' => (bool) ($flags['is_free_trial'] ?? \false)));
        // Make sure "paypal" is never disabled in the funding-sources.
        $disable_funding = array_filter($disable_funding, static fn(string $funding_source) => $funding_source !== 'paypal');
        return array_unique($disable_funding);
    }
}
