<?php

/**
 * AxoApplies helper.
 * Checks if AXO is available for a given country and currency.
 *
 * @package WooCommerce\PayPalCommerce\Axo\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Axo\Service;

use WooCommerce\PayPalCommerce\ApiClient\Helper\CurrencyGetter;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CardPaymentsConfiguration;
use WooCommerce\PayPalCommerce\WcGateway\Helper\CartCheckoutDetector;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
class AxoApplies
{
    /**
     * The matrix which countries and currency combinations can be used for AXO.
     *
     * @var array
     */
    private $allowed_country_currency_matrix;
    /**
     * The getter of the 3-letter currency code of the shop.
     *
     * @var CurrencyGetter
     */
    private CurrencyGetter $currency;
    /**
     * 2-letter country code of the shop.
     *
     * @var string
     */
    private $country;
    private CardPaymentsConfiguration $dcc_configuration;
    private SubscriptionHelper $subscription_helper;
    /**
     * DccApplies constructor.
     *
     * @param array          $allowed_country_currency_matrix The matrix which countries and currency combinations can be used for AXO.
     * @param CurrencyGetter $currency The getter of the 3-letter currency code of the shop.
     * @param string         $country 2-letter country code of the shop.
     */
    public function __construct(array $allowed_country_currency_matrix, CurrencyGetter $currency, string $country, CardPaymentsConfiguration $dcc_configuration, SubscriptionHelper $subscription_helper)
    {
        $this->allowed_country_currency_matrix = $allowed_country_currency_matrix;
        $this->currency = $currency;
        $this->country = $country;
        $this->dcc_configuration = $dcc_configuration;
        $this->subscription_helper = $subscription_helper;
    }
    /**
     * Returns whether AXO can be used in the current country and the current currency used.
     *
     * @return bool
     */
    public function for_country_currency(): bool
    {
        if (!in_array($this->country, array_keys($this->allowed_country_currency_matrix), \true)) {
            return \false;
        }
        return in_array($this->currency->get(), $this->allowed_country_currency_matrix[$this->country], \true);
    }
    /**
     * Indicates, whether the current merchant is eligible for Fastlane. Always true,
     * but the filter allows other modules to disable Fastlane site-wide.
     *
     * @return bool
     */
    public function for_merchant(): bool
    {
        return apply_filters('woocommerce_paypal_payments_is_eligible_for_axo', \true);
    }
    /**
     * Checks if Fastlane should be rendered.
     *
     * @return bool
     */
    public function should_render_fastlane(): bool
    {
        return !is_user_logged_in() && CartCheckoutDetector::has_classic_checkout() && $this->dcc_configuration->use_fastlane() && !$this->is_excluded_endpoint() && is_checkout() && !$this->subscription_helper->cart_contains_subscription();
    }
    /**
     * Condition to evaluate if the current endpoint is excluded.
     *
     * @return bool
     */
    private function is_excluded_endpoint(): bool
    {
        return is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received');
    }
}
