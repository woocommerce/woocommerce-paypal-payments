<?php

/**
 * The wrapper for retrieving the invoice prefix as late as possible,
 * so that settings changes and filters are not lost to early caching in services.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
/**
 * Class InvoicePrefixGetter
 */
class InvoicePrefixGetter
{
    private SettingsProvider $settings;
    public function __construct(SettingsProvider $settings)
    {
        $this->settings = $settings;
    }
    /**
     * Returns the invoice prefix. An empty string means "no prefix".
     */
    public function get(): string
    {
        $prefix = $this->settings->invoice_prefix();
        /**
         * Filters the prefix of invoice IDs and fabricated vault customer IDs.
         *
         * @param string $prefix The invoice prefix configured by the merchant.
         */
        $filtered = apply_filters('woocommerce_paypal_payments_invoice_prefix', $prefix);
        return is_string($filtered) ? $filtered : $prefix;
    }
    /**
     * Returns the invoice prefix.
     */
    public function __toString()
    {
        return $this->get();
    }
}
