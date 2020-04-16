<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\ApiClient\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Authentication\Bearer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\ErrorResponse;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Payer;
use Inpsyde\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\ApiClient\Factory\ErrorResponseCollectionFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\OrderFactory;
use Inpsyde\PayPalCommerce\ApiClient\Factory\PatchCollectionFactory;

class OrderEndpoint
{

    private $host;
    private $bearer;
    private $orderFactory;
    private $patchCollectionFactory;
    private $intent;
    private $errorResponseFactory;

    public function __construct(
        string $host,
        Bearer $bearer,
        OrderFactory $orderFactory,
        PatchCollectionFactory $patchCollectionFactory,
        string $intent,
        ErrorResponseCollectionFactory $errorResponseFactory
    ) {

        $this->host = $host;
        $this->bearer = $bearer;
        $this->orderFactory = $orderFactory;
        $this->patchCollectionFactory = $patchCollectionFactory;
        $this->intent = $intent;
        $this->errorResponseFactory = $errorResponseFactory;
    }

    public function createForPurchaseUnits(array $items, Payer $payer = null) : Order
    {
        $items = array_filter(
            $items,
            function($item) : bool {
                return is_a($item, PurchaseUnit::class);
            }
        );
        $bearer = $this->bearer->bearer();
        $data = [
            'intent' => $this->intent,
            'purchase_units' => array_map(
                function (PurchaseUnit $item) : array {
                    return $item->toArray();
                },
                $items
            ),
        ];
        if ($payer) {
            $data['payer'] = $payer->toArray();
        }
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
        if (is_wp_error($response)) {
            $this->handleResponseWpError($url, $args);
            throw new RuntimeException(__('Could not create order.', 'woocommerce-paypal-commerce-gateway'));
        }
        $json = json_decode($response['body']);
        if (wp_remote_retrieve_response_code($response) !== 201) {
            $errors = $this->errorResponseFactory->fromPayPalResponse(
                $json,
                (int)wp_remote_retrieve_response_code($response),
                $url,
                $args
            );
            do_action('woocommerce-paypal-commerce-gateway.error', $errors);
            throw new RuntimeException(__('Could not create order.', 'woocommerce-paypal-commerce-gateway'));
        }
        $order = $this->orderFactory->fromPayPalResponse($json);
        return $order;
    }

    public function capture(Order $order) : Order
    {
        if ($order->status()->is(OrderStatus::COMPLETED)) {
            return $order;
        }
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/checkout/orders/' . $order->id() . '/capture';
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
        ];
        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $this->handleResponseWpError($url, $args);
            throw new RuntimeException(__('Could not capture order.', 'woocommerce-paypal-commerce-gateway'));
        }
        $json = json_decode($response['body']);
        if (wp_remote_retrieve_response_code($response) !== 201) {
            $errors = $this->errorResponseFactory->fromPayPalResponse(
                $json,
                (int)wp_remote_retrieve_response_code($response),
                $url,
                $args
            );

            // If the order has already been captured, we return the updated order.
            if ($errors->hasErrorCode(ErrorResponse::ORDER_ALREADY_CAPTURED)) {
                return $this->order($order->id());
            }
            do_action('woocommerce-paypal-commerce-gateway.error', $errors);
            throw new RuntimeException(__('Could not capture order.', 'woocommerce-paypal-commerce-gateway'));
        }
        $json = json_decode($response['body']);
        $order = $this->orderFactory->fromPayPalResponse($json);
        return $order;
    }

    public function authorize(Order $order): Order
    {
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/checkout/orders/' . $order->id() . '/authorize';
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
        ];
        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $this->handleResponseWpError($url, $args);
            throw new RuntimeException(
                __(
                    'Could not authorize order.',
                    'woocommerce-paypal-commerce-gateway'
                )
            );
        }
        $json = json_decode($response['body']);
        if (wp_remote_retrieve_response_code($response) !== 201) {
            $errors = $this->errorResponseFactory->fromPayPalResponse(
                $json,
                (int)wp_remote_retrieve_response_code($response),
                $url,
                $args
            );

            if ($errors->hasErrorCode(ErrorResponse::ORDER_ALREADY_AUTHORIZED)) {
                return $this->order($order->id());
            }
            add_action('woocommerce-paypal-commerce-gateway.error', $errors);
            throw new RuntimeException(
                __(
                    'Could not authorize order.',
                    'woocommerce-paypal-commerce-gateway'
                )
            );
        }
        $order = $this->orderFactory->fromPayPalResponse($json);
        return $order;
    }

    public function order(string $id) : Order
    {
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/checkout/orders/' . $id;
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer,
                'Content-Type' => 'application/json',
            ],
        ];
        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            $this->handleResponseWpError($url, $args);
            throw new RuntimeException(__('Could not retrieve order.', 'woocommerce-paypal-commerce-gateway'));
        }
        $json = json_decode($response['body']);
        if (wp_remote_retrieve_response_code($response) !== 200) {
            $errors = $this->errorResponseFactory->fromPayPalResponse(
                $json,
                (int) wp_remote_retrieve_response_code($response),
                $url,
                $args
            );
            do_action('woocommerce-paypal-commerce-gateway.error', $errors);
            throw new RuntimeException(__('Could not retrieve order.', 'woocommerce-paypal-commerce-gateway'));
        }
        return $this->orderFactory->fromPayPalResponse($json);
    }

    public function patchOrderWith(Order $orderToUpdate, Order $orderToCompare) : Order
    {
        $patches = $this->patchCollectionFactory->fromOrders($orderToUpdate, $orderToCompare);
        if (! count($patches->patches())) {
            return $orderToUpdate;
        }

        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/checkout/orders/' . $orderToUpdate->id();
        $args = [
            'method' => 'PATCH',
            'headers' => [
                'Authorization' => 'Bearer ' . $bearer,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=representation',
            ],
            'body' => json_encode($patches->toArray()),
        ];
        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            $this->handleResponseWpError($url, $args);
            throw new RuntimeException(__('Could not retrieve order.', 'woocommerce-paypal-commerce-gateway'));
        }
        if (wp_remote_retrieve_response_code($response) !== 204) {
            $json = json_decode($response['body']);
            $errors = $this->errorResponseFactory->fromPayPalResponse(
                $json,
                (int)wp_remote_retrieve_response_code($response),
                $url,
                $args
            );
            do_action('woocommerce-paypal-commerce-gateway.error', $errors);
            throw new RuntimeException(__('Could not patch order.', 'woocommerce-paypal-commerce-gateway'));
        }

        $newOrder = $this->order($orderToUpdate->id());
        return $newOrder;
    }

    private function handleResponseWpError(string $url, array $args)
    {
        $errors = $this->errorResponseFactory->unknownError(
            $url,
            $args
        );
        do_action('woocommerce-paypal-commerce-gateway.error', $errors);
    }
}
