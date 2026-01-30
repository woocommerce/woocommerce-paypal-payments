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
    }
    public function get(string $key): ?\WooCommerce\PayPalCommerce\Button\Session\CartData
    {
        $data = get_transient($key);
        if (!is_array($data)) {
            return null;
        }
        return \WooCommerce\PayPalCommerce\Button\Session\CartData::from_array($data, $key);
    }
    public function remove(\WooCommerce\PayPalCommerce\Button\Session\CartData $cart_data): void
    {
        $key = $cart_data->key();
        if (empty($key)) {
            return;
        }
        delete_transient($key);
    }
}
