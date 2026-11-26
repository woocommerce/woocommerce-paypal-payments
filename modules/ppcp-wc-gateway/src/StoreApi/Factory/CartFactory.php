<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory;

use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\Cart;
/**
 * Factory for the Store API cart.
 */
class CartFactory
{
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\CartTotalsFactory $cart_totals_factory;
    private \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\ShippingRatesFactory $shipping_rates_factory;
    public function __construct(\WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\CartTotalsFactory $cart_totals_factory, \WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\ShippingRatesFactory $shipping_rate_factory)
    {
        $this->cart_totals_factory = $cart_totals_factory;
        $this->shipping_rates_factory = $shipping_rate_factory;
    }
    public function from_response(array $obj): Cart
    {
        return new Cart($this->cart_totals_factory->from_response_obj((array) ($obj['totals'] ?? array())), $this->shipping_rates_factory->from_response_obj((array) ($obj['shipping_rates'] ?? array())));
    }
}
