<?php

/**
 * Returns shipping_preference for the given state.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use WC_Cart;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ExperienceContext;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
/**
 * Class ShippingPreferenceFactory
 */
class ShippingPreferenceFactory
{
    /**
     * Returns shipping_preference for the given state.
     *
     * @param PurchaseUnit $purchase_unit The PurchaseUnit.
     * @param string       $context The operation context like 'checkout', 'cart'.
     * @param WC_Cart|null $cart The current cart if relevant.
     * @param string       $funding_source The funding source (PayPal button) like 'paypal', 'venmo', 'card'.
     * @return string
     */
    public function from_state(PurchaseUnit $purchase_unit, string $context, ?WC_Cart $cart = null, string $funding_source = '', ?WC_Order $wc_order = null): string
    {
        /**
         *  If you are using this filter to set 'NO_SHIPPING', you may also want to disable sending
         *  shipping fields completely.
         *
         * @see PurchaseUnitFactory::shipping_needed() for
         *  the woocommerce_paypal_payments_shipping_needed filter.
         *
         * @see ExperienceContext for SHIPPING_PREFERENCE_* constants.
         * @see https://developer.paypal.com/serversdk/php/models/enumerations/shipping-preference/
         */
        $shipping_preference = apply_filters('woocommerce_paypal_payments_shipping_preference', null, $purchase_unit, $context, $cart, $funding_source);
        if (is_string($shipping_preference)) {
            return $shipping_preference;
        }
        $contains_physical_goods = $purchase_unit->contains_physical_goods();
        if (!$contains_physical_goods) {
            return ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING;
        }
        $has_shipping = null !== $purchase_unit->shipping();
        $needs_shipping = $wc_order && $this->wc_order_needs_shipping($wc_order) || $cart && $cart->needs_shipping();
        $shipping_address_is_fixed = $needs_shipping && in_array($context, array('checkout', 'pay-now'), \true);
        if (!$needs_shipping) {
            return ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING;
        }
        if ($shipping_address_is_fixed) {
            // Checkout + no address given? Probably something weird happened, like no form validation?
            if (!$has_shipping) {
                return ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING;
            }
            return ExperienceContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS;
        }
        if ('card' === $funding_source) {
            if (!$has_shipping) {
                return ExperienceContext::SHIPPING_PREFERENCE_NO_SHIPPING;
            }
            // Looks like GET_FROM_FILE does not work for the vaulted card button.
            return ExperienceContext::SHIPPING_PREFERENCE_SET_PROVIDED_ADDRESS;
        }
        return ExperienceContext::SHIPPING_PREFERENCE_GET_FROM_FILE;
    }
    protected function wc_order_needs_shipping(WC_Order $wc_order): bool
    {
        // WC 9.9.0+.
        if (method_exists($wc_order, 'needs_shipping')) {
            return $wc_order->needs_shipping();
        }
        if (!wc_shipping_enabled() || wc_get_shipping_method_count(\true) === 0) {
            return \false;
        }
        foreach ($wc_order->get_items() as $item) {
            if ($item instanceof WC_Order_Item_Product) {
                $product = $item->get_product();
                if ($product instanceof WC_Product && $product->needs_shipping()) {
                    return \true;
                }
            }
        }
        return \false;
    }
}
