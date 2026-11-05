<?php

/**
 * Checks eligibility for Level 2/3 card processing.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
class PaymentLevelEligibility
{
    protected string $country;
    protected \WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter $shop_currency;
    public function __construct(string $country, \WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter $shop_currency)
    {
        $this->country = $country;
        $this->shop_currency = $shop_currency;
    }
    /**
     * Checks if payment is eligible for Level 2/3 processing.
     *
     * @param string $payment_method
     * @return bool True if eligible.
     */
    public function is_eligible(string $payment_method): bool
    {
        if (!$this->is_valid_country()) {
            return \false;
        }
        if (!$this->is_valid_currency()) {
            return \false;
        }
        if (!$this->is_valid_payment_method($payment_method)) {
            return \false;
        }
        /**
         * Filters whether the payment is eligible for Level 2/3 processing.
         *
         * @param bool $is_eligible Whether the payment is eligible.
         */
        return apply_filters('woocommerce_paypal_payments_level_processing_eligible', \true);
    }
    private function is_valid_country(): bool
    {
        /**
         * Filters the allowed countries for Level 2/3 processing.
         *
         * @param array $countries Array of allowed country codes.
         */
        $allowed_countries = apply_filters('woocommerce_paypal_payments_level_processing_countries', array('US'));
        return in_array($this->country, $allowed_countries, \true);
    }
    private function is_valid_currency(): bool
    {
        /**
         * Filters the allowed currencies for Level 2/3 processing.
         *
         * @param array $currencies Array of allowed currency codes.
         */
        $allowed_currencies = apply_filters('woocommerce_paypal_payments_level_processing_currencies', array('USD'));
        return in_array($this->shop_currency->get(), $allowed_currencies, \true);
    }
    private function is_valid_payment_method(string $payment_method): bool
    {
        /**
         * Filters the allowed payment methods for Level 2/3 processing.
         *
         * @param array $methods Array of allowed payment method IDs.
         */
        $allowed_methods = apply_filters('woocommerce_paypal_payments_level_processing_payment_methods', array(CreditCardGateway::ID));
        return in_array($payment_method, $allowed_methods, \true);
    }
}
