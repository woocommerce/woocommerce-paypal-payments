<?php

/**
 * Creates the list of disabled funding sources.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Helper;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcSubscriptions\FreeTrialHandlerTrait;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CartCheckoutDetector;
class DisabledFundingSources
{
    use FreeTrialHandlerTrait;
    private SettingsProvider $settings_provider;
    private array $all_funding_sources;
    private CardPaymentsConfiguration $dcc_configuration;
    private string $merchant_country;
    public function __construct(SettingsProvider $settings_provider, array $all_funding_sources, CardPaymentsConfiguration $dcc_configuration, string $merchant_country)
    {
        $this->settings_provider = $settings_provider;
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
        $disable_funding = $this->get_sources_from_settings($context);
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
    private function get_sources_from_settings(string $context): array
    {
        $disabled_funding = array();
        $methods = $this->settings_provider->button_styling($context)->methods;
        if (!$this->settings_provider->venmo_enabled() || !in_array('venmo', $methods, \true)) {
            $disabled_funding[] = 'venmo';
        }
        /**
         * Filters the list of disabled funding methods.
         *
         * This filter allows merchants to programmatically disable funding sources.
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
