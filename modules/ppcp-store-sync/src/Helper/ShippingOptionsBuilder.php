<?php

/**
 * Builds the available_shipping_options array for cart API responses.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Helper;

use WC_Cart;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;
use WooCommerce\PayPalCommerce\StoreSync\Schema\Money;
class ShippingOptionsBuilder
{
    private StoreCurrencyValue $store_currency;
    public function __construct(StoreCurrencyValue $store_currency)
    {
        $this->store_currency = $store_currency;
    }
    /**
     * Build shipping options from the WC cart state post calculate_totals().
     *
     * Returns an empty array when no cart is available or no packages exist.
     * Exactly one option in a non-empty result has is_selected = true.
     *
     * @param WC_Cart|null $wc_cart The WooCommerce cart.
     * @return array
     */
    public function build(?WC_Cart $wc_cart): array
    {
        if (null === $wc_cart) {
            return array();
        }
        $packages = WC()->shipping()->get_packages();
        // Note: This plugin only supports a single package.
        // Key constraint is the PayPal API that can receive one shipping address per order.
        $package = $packages[0] ?? null;
        if (empty($package)) {
            return array();
        }
        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        $chosen_id = null;
        // The CartResponse schema expects only one selected shipping option.
        // If we have _any_ options, we pluck the first value as it's always present,
        // even when the session incorrectly contains multiple chosen methods.
        // We silently ignore cases where more than one option is chosen in the session.
        if (is_array($chosen_methods) && !empty($chosen_methods)) {
            $chosen_id = $chosen_methods[0];
        }
        $currency = $this->store_currency->value();
        $options = array();
        $all_rates = $package['rates'] ?? array();
        $first_rate_id = null;
        foreach ($all_rates as $rate) {
            // Note: Rate IDs can repeat in different packages, e.g. "flat_rate:1" can apply
            // to all packages. We do not care about this potential repeat ID, for 2 reasons:
            // 1. The PayPal API only supports single-package orders.
            // 2. The PayPalCart only supports a single shipping method.
            $rate_id = $rate->get_id();
            if (null === $first_rate_id) {
                $first_rate_id = $rate_id;
            }
            $options[] = array('id' => $rate_id, 'name' => $rate->get_label(), 'price' => Money::create($rate->get_cost(), $currency)->to_array(), 'is_selected' => \false);
        }
        if (empty($options)) {
            return array();
        }
        $selected_id = $chosen_id ?? $first_rate_id;
        foreach ($options as &$option) {
            $option['is_selected'] = $option['id'] === $selected_id;
        }
        unset($option);
        return $options;
    }
}
