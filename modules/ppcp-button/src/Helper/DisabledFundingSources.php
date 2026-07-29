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
            return $this->sanitize_and_filter_sources($this->get_sources_for_free_trial($flags), $flags);
        }
        $disable_funding = $this->get_sources_from_settings($context);
        $disable_funding = $this->apply_card_rules($disable_funding, $flags);
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
     * The 'card' decision defers to {@see self::should_disable_card()} so the
     * same decision table applies to free-trial carts — notably: classic
     * checkout keeps 'card' enabled for ACDC (card-fields) or BCDC (card
     * button); block checkout keeps 'card' disabled because ACDC there is
     * rendered via the WC Blocks integration.
     *
     * @param array $flags Decision flags (context, is_block_context, …).
     * @return array
     */
    private function get_sources_for_free_trial(array $flags): array
    {
        // Disable all sources.
        $disable_funding = array_keys($this->all_funding_sources);
        if (!$this->should_disable_card((bool) ($flags['is_block_context'] ?? \false))) {
            $disable_funding = array_filter($disable_funding, static fn(string $funding_source) => $funding_source !== 'card');
        }
        return $disable_funding;
    }
    /**
     * Applies the 'card' funding-source rules as a single decision.
     *
     * This is the single authority for whether 'card' is disabled.
     * No other module should add or remove 'card' via the disabled-funding
     * filters, except the branded-only correction in SettingsModule
     * (which depends on PaymentSettings gateway state unavailable here).
     *
     * @param array $disable_funding The current disabled funding sources.
     * @param array $flags           Decision flags (context, is_block_context, …).
     * @return array
     */
    private function apply_card_rules(array $disable_funding, array $flags): array
    {
        if ($this->should_disable_card($flags['is_block_context'])) {
            $disable_funding[] = 'card';
        }
        return $disable_funding;
    }
    /**
     * Determines whether the 'card' funding source should be disabled.
     *
     * This is the single authority for the 'card' funding-source decision.
     * No other module should add or remove 'card' via disabled-funding filters.
     *
     * Decision table:
     *
     *  Non-checkout page              → disabled  (no card button/fields needed outside checkout)
     *  Block checkout + ACDC enabled  → disabled  (ACDC uses WC Blocks card-fields, not this source)
     *  Block checkout + BCDC          → enabled   (BCDC card button shown in blocks)
     *  Block checkout, neither        → disabled
     *  MX + BCDC + classic            → enabled   (country-specific override)
     *  Classic checkout + ACDC        → enabled   (card-fields component needs this source)
     *  Classic checkout + BCDC        → enabled   (card button needs this source)
     *  Classic checkout, neither      → disabled
     *
     * Note: uses is_acdc_enabled() (gateway actually on), not use_acdc() (capability only),
     * so a MX merchant with BCDC on but ACDC gateway off still gets the BCDC button.
     *
     * @param bool $is_block_context Whether the current render context is a block.
     * @return bool True when 'card' should be added to the disabled list.
     */
    private function should_disable_card(bool $is_block_context): bool
    {
        // Non-checkout pages never need a card button or card fields.
        if (!is_checkout()) {
            return \true;
        }
        if ($is_block_context) {
            // In block checkout, ACDC is rendered via the WC Blocks integration —
            // it does not use the 'card' PayPal SDK funding source.
            // Keep 'card' enabled only when BCDC is active and ACDC is not actually enabled.
            return $this->dcc_configuration->is_acdc_enabled() || !$this->dcc_configuration->is_bcdc_enabled();
        }
        // Mexico + BCDC + classic checkout: country-level override keeps card enabled.
        if ('MX' === $this->merchant_country && $this->dcc_configuration->is_bcdc_enabled() && CartCheckoutDetector::has_classic_checkout()) {
            return \false;
        }
        // Classic checkout: keep 'card' enabled for ACDC (card-fields) or BCDC (card button).
        return !$this->dcc_configuration->is_acdc_enabled() && !$this->dcc_configuration->is_bcdc_enabled();
    }
    /**
     * Applies special rules for block checkout.
     *
     * Block checkout only supports: PayPal, PayLater, Venmo, and conditionally card (BCDC).
     * All other funding methods are disabled here.
     *
     * @param array $disable_funding The current disabled funding sources.
     * @return array
     */
    private function apply_block_checkout_rules(array $disable_funding): array
    {
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
