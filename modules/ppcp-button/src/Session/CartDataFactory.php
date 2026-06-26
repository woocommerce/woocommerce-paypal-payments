<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Session;

use Exception;
use WC_Cart;
/**
 * Creates CartData.
 */
class CartDataFactory
{
    /**
     * Creates CartData from the WC cart.
     *
     * @throws Exception If WC cart is missing.
     */
    public function from_current_cart(?WC_Cart $cart = null): \WooCommerce\PayPalCommerce\Button\Session\CartData
    {
        if (!$cart) {
            $cart = WC()->cart;
            if (!$cart instanceof WC_Cart) {
                throw new Exception('Cart not found.');
            }
        }
        $cart_data = new \WooCommerce\PayPalCommerce\Button\Session\CartData($cart->get_cart_for_session(), $cart->get_applied_coupons(), $cart->needs_shipping(), get_current_user_id(), $cart->get_cart_hash());
        if (WC()->session) {
            $cart_data->set_session_customer_id((string) WC()->session->get_customer_id());
        }
        return $cart_data;
    }
}
