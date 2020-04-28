<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\Session\SessionHandler;

class ApproveOrderEndpoint implements EndpointInterface
{

    const ENDPOINT = 'ppc-approve-order';

    private $requestData;
    private $sessionHandler;
    private $apiEndpoint;
    public function __construct(
        RequestData $requestData,
        OrderEndpoint $apiEndpoint,
        SessionHandler $sessionHandler
    ) {

        $this->requestData = $requestData;
        $this->apiEndpoint = $apiEndpoint;
        $this->sessionHandler = $sessionHandler;
    }

    public static function nonce(): string
    {
        return self::ENDPOINT;
    }

    public function handleRequest(): bool
    {
        try {
            $data = $this->requestData->readRequest($this->nonce());
            if (! isset($data['order_id'])) {
                throw new RuntimeException(__("No order id given", "woocommerce-paypal-commerce-gateway"));
            }

            $order = $this->apiEndpoint->order($data['order_id']);
            if (! $order) {
                throw new RuntimeException(
                    sprintf(
                        // translators: %s is the id of the order.
                        __('Order %s not found.', 'woocommerce-paypal-commerce-gateway'),
                        $data['order_id']
                    )
                );
            }

            if (! $order->status()->is(OrderStatus::APPROVED)) {
                throw new RuntimeException(
                    sprintf(
                    // translators: %s is the id of the order.
                        __('Order %s is not approved yet.', 'woocommerce-paypal-commerce-gateway'),
                        $data['order_id']
                    )
                );
            }

            $this->sessionHandler->replaceOrder($order);
            wp_send_json_success($order);
            return true;
        } catch (\RuntimeException $error) {
            wp_send_json_error($error->getMessage());
            return false;
        }
    }
}
