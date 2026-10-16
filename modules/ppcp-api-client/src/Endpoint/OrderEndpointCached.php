<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
/**
 * Extends OrderEndpoint to provide in-memory caching for order() calls.
 */
class OrderEndpointCached extends \WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint
{
    /**
     * In-memory cache for orders, keyed by PayPal order ID.
     *
     * @var array<string, Order>
     */
    private array $order_cache = array();
    /**
     * Fetches an order for a given ID, with in-memory caching.
     *
     * @param string|WC_Order $paypal_id_or_wc_order The ID of PayPal order or a WC order (with the ID in meta).
     *
     * @return Order
     */
    public function order($paypal_id_or_wc_order): Order
    {
        $paypal_id = $this->resolve_paypal_id($paypal_id_or_wc_order);
        if (isset($this->order_cache[$paypal_id])) {
            return $this->order_cache[$paypal_id];
        }
        $order = parent::order($paypal_id);
        $this->order_cache[$paypal_id] = $order;
        return $order;
    }
}
