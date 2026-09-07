<?php

/**
 * The wrapper for retrieving shop currency as late as possible,
 * to avoid early caching in services, e.g. before multi-currency filters were added.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

/**
 * Class CurrencyGetter
 */
class CurrencyGetter
{
    /**
     * Returns the WC currency.
     */
    public function get(): string
    {
        $currency = get_woocommerce_currency();
        if ($currency) {
            return $currency;
        }
        $currency = get_option('woocommerce_currency');
        if (!$currency) {
            return 'NO_CURRENCY';
            // Unlikely to happen.
        }
        return $currency;
    }
    /**
     * Returns the WC currency.
     */
    public function __toString()
    {
        return $this->get();
    }
}
