<?php

/**
 * PayPal order repository.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Repository
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Repository;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
/**
 * Class OrderRepository
 */
class OrderRepository
{
    /**
     * The order endpoint.
     *
     * @var OrderEndpoint
     */
    protected $order_endpoint;
    /**
     * OrderRepository constructor.
     *
     * @param OrderEndpoint $order_endpoint The order endpoint.
     */
    public function __construct(OrderEndpoint $order_endpoint)
    {
        $this->order_endpoint = $order_endpoint;
    }
    /**
     * Gets a PayPal order for the given WooCommerce order.
     *
     * @param WC_Order $wc_order The WooCommerce order.
     * @return Order The PayPal order.
     * @throws RuntimeException When there is a problem getting the PayPal order.
     */
    public function for_wc_order(WC_Order $wc_order): Order
    {
        $paypal_order_id = $wc_order->get_meta(PayPalGateway::ORDER_ID_META_KEY);
        if (!$paypal_order_id) {
            throw new RuntimeException('PayPal order ID not found in meta.');
        }
        return $this->order_endpoint->order($paypal_order_id);
    }
}
