<?php

/**
 * Factory service for the REST response objects.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Response
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Response;

use WC_Order;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\AppliedCouponsBuilder;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ShippingOptionsBuilder;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
class ResponseFactory
{
    private AppliedCouponsBuilder $applied_coupons_builder;
    private ShippingOptionsBuilder $shipping_options_builder;
    public function __construct(AppliedCouponsBuilder $applied_coupons_builder, ShippingOptionsBuilder $shipping_options_builder)
    {
        $this->applied_coupons_builder = $applied_coupons_builder;
        $this->shipping_options_builder = $shipping_options_builder;
    }
    /**
     * Create a new cart response (status: CREATED).
     *
     * @param StorePayPalCart $store_cart The enriched cart.
     * @param string          $cart_id    The cart ID.
     * @return CartResponse The response object.
     */
    public function new_cart(StorePayPalCart $store_cart, string $cart_id): \WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse
    {
        return \WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse::create_new($store_cart, $cart_id)->applied_coupons($this->build_applied_coupons($store_cart))->shipping_options($this->shipping_options_builder->build($store_cart->wc_cart()));
    }
    /**
     * Create a paid cart response.
     *
     * @param WC_Order        $order      The WooCommerce order.
     * @param StorePayPalCart $store_cart The enriched cart.
     * @param string          $cart_id    The cart ID.
     * @return CartResponse The response object.
     */
    public function from_order(WC_Order $order, StorePayPalCart $store_cart, string $cart_id): \WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse
    {
        return \WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse::create_completed($store_cart, $cart_id, $order)->applied_coupons($this->build_applied_coupons($store_cart))->shipping_options($this->shipping_options_builder->build($store_cart->wc_cart()));
    }
    /**
     * Create a basic cart response.
     *
     * @param StorePayPalCart $store_cart The enriched cart.
     * @param string          $cart_id    The cart ID.
     * @return CartResponse The response object.
     */
    public function from_cart(StorePayPalCart $store_cart, string $cart_id): \WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse
    {
        return \WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse::create($store_cart, $cart_id)->applied_coupons($this->build_applied_coupons($store_cart))->shipping_options($this->shipping_options_builder->build($store_cart->wc_cart()));
    }
    /**
     * Build applied coupons data for a cart.
     *
     * @param StorePayPalCart $store_cart The enriched cart.
     * @return array Applied coupons data.
     */
    private function build_applied_coupons(StorePayPalCart $store_cart): array
    {
        $validation_status = $store_cart->validation()->is_empty() ? 'VALID' : 'INVALID';
        return $this->applied_coupons_builder->build_applied_coupons_array($store_cart, $validation_status);
    }
}
