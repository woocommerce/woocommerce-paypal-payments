<?php

/**
 * Whether the v6 SDK may load for the merchant's country.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6\Helper;

/**
 * Mexican merchants are served the v5 stack.
 */
class MerchantCountrySupport
{
    /**
     * The merchant's two-letter country code.
     */
    private string $merchant_country;
    public function __construct(string $merchant_country)
    {
        $this->merchant_country = $merchant_country;
    }
    public function is_supported(): bool
    {
        /**
         * Filters the merchant countries the v6 SDK is withheld from.
         *
         * @param string[] $countries Two-letter country codes.
         */
        $countries = apply_filters('woocommerce_paypal_payments_sdk_v6_unsupported_countries', array('MX'));
        return !in_array($this->merchant_country, (array) $countries, \true);
    }
}
