<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Shipping;

use WooCommerce\PayPalCommerce\WcGateway\Endpoint\ShippingCallbackEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Endpoint\CartEndpoint;
/**
 * URL generation for the server-side shipping callback.
 */
class ShippingCallbackUrlFactory
{
    private CartEndpoint $cart_endpoint;
    private ShippingCallbackEndpoint $shipping_callback_endpoint;
    public function __construct(CartEndpoint $cart_endpoint, ShippingCallbackEndpoint $shipping_callback_endpoint)
    {
        $this->cart_endpoint = $cart_endpoint;
        $this->shipping_callback_endpoint = $shipping_callback_endpoint;
    }
    /**
     * Creates the callback URL.
     */
    public function create(): string
    {
        $cart_response = $this->cart_endpoint->get_cart();
        $url = $this->shipping_callback_endpoint->url();
        $url = add_query_arg('cart_token', $cart_response->cart_token(), $url);
        return $url;
    }
}
