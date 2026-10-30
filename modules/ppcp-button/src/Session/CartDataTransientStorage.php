<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Session;

use Exception;
/**
 * Handles saving of CartData into WP transients.
 */
class CartDataTransientStorage
{
    protected int $expiration = 2 * HOUR_IN_SECONDS;
    /**
     * @throws Exception If saving failed.
     */
    public function save(\WooCommerce\PayPalCommerce\Button\Session\CartData $cart_data): void
    {
        if (empty($cart_data->key())) {
            $cart_data->generate_key();
        }
        $key = $cart_data->key();
        assert(!empty($key));
        if (!set_transient($key, $cart_data->to_array(), $this->expiration)) {
            throw new Exception('set_transient failed.');
        }
        // Create reverse lookup by PayPal order ID if available.
        $paypal_order_id = $cart_data->paypal_order_id();
        if (!empty($paypal_order_id)) {
            set_transient('ppcp_cart_by_order_' . $paypal_order_id, $key, $this->expiration);
        }
    }
    public function get(string $key): ?\WooCommerce\PayPalCommerce\Button\Session\CartData
    {
        $data = get_transient($key);
        if (!is_array($data)) {
            return null;
        }
        return \WooCommerce\PayPalCommerce\Button\Session\CartData::from_array($data, $key);
    }
    /**
     * Gets CartData by PayPal order ID.
     *
     * @param string $paypal_order_id The PayPal order ID.
     * @return CartData|null The CartData if found, null otherwise.
     */
    public function get_by_paypal_order_id(string $paypal_order_id): ?\WooCommerce\PayPalCommerce\Button\Session\CartData
    {
        $cart_key = get_transient('ppcp_cart_by_order_' . $paypal_order_id);
        if (empty($cart_key) || !is_string($cart_key)) {
            return null;
        }
        return $this->get($cart_key);
    }
    public function remove(\WooCommerce\PayPalCommerce\Button\Session\CartData $cart_data): void
    {
        $key = $cart_data->key();
        if (!empty($key)) {
            delete_transient($key);
        }
        // Also delete reverse lookup by PayPal order ID.
        $paypal_order_id = $cart_data->paypal_order_id();
        if (!empty($paypal_order_id)) {
            delete_transient('ppcp_cart_by_order_' . $paypal_order_id);
        }
    }
}
