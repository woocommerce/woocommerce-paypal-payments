<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;


use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\LineItem;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\Session\SessionHandler;

class OrderEndpoint
{

    private $host;
    private $bearer;
    private $sessionHandler;
    public function __construct(string $host, Bearer $bearer, SessionHandler $sessionHandler)
    {
        $this->host = $host;
        $this->bearer = $bearer;
        $this->sessionHandler = $sessionHandler;
    }

    public function createForLineItems(LineItem ...$items) : ?Order {

        $bearer = $this->bearer->bearer();

        $data = [
            'intent' => 'CAPTURE',
            'purchase_units' => array_map(
                function(LineItem $item) : array {
                    return $item->toArray();
                },
                $items
            ),
        ];
        $url = trailingslashit($this->host) . 'v2/checkout/orders';
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
            'body' => json_encode($data),
        ];
        $response = wp_remote_post($url, $args);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 201) {
            throw new RuntimeException(__('Could not create order.', 'woocommerce-paypal-commerce-gateway'));
        }
        $json = json_decode($response['body']);
        $order = new Order($json);
        $this->sessionHandler->setOrder($order);
        return $order;
    }

    public function capture(Order $order) : Order
    {

        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/checkout/orders/' . $order->id() . '/capture';
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ]
        ];
        $response = wp_remote_post($url, $args);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 201) {
            throw new RuntimeException(__('Could not capture order.', 'woocommerce-paypal-commerce-gateway'));
        }
        $json = json_decode($response['body']);
        $order = new Order($json);
        $this->sessionHandler->setOrder($order);
        return $order;
    }
}