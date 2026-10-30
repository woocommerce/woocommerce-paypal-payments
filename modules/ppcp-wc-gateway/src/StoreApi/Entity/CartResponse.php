<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity;

/**
 * CartResponse for the Store API.
 */
class CartResponse
{
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Cart $cart;
    private string $cart_token;
    public function __construct(\WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Cart $cart, string $cart_token)
    {
        $this->cart = $cart;
        $this->cart_token = $cart_token;
    }
    public function cart(): \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Cart
    {
        return $this->cart;
    }
    /**
     * The token required for the API requests (except Get Cart).
     */
    public function cart_token(): string
    {
        return $this->cart_token;
    }
}
