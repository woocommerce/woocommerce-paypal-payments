<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Entity\Order;
use Inpsyde\PayPalCommerce\ApiClient\Entity\OrderStatus;
use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;
use Inpsyde\PayPalCommerce\Button\Helper\ThreeDSecure;
use Inpsyde\PayPalCommerce\Session\SessionHandler;

class ApproveOrderEndpoint implements EndpointInterface
{

    public const ENDPOINT = 'ppc-approve-order';

    private $requestData;
    private $sessionHandler;
    private $apiEndpoint;
    private $threedSecure;
    public function __construct(
        RequestData $requestData,
        OrderEndpoint $apiEndpoint,
        SessionHandler $sessionHandler,
        ThreeDSecure $threedSecure
    ) {

        $this->requestData = $requestData;
        $this->apiEndpoint = $apiEndpoint;
        $this->sessionHandler = $sessionHandler;
        $this->threedSecure = $threedSecure;
    }

    public static function nonce(): string
    {
        return self::ENDPOINT;
    }

    //phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong
    public function handleRequest(): bool
    {
        try {
            $data = $this->requestData->readRequest($this->nonce());
            if (! isset($data['order_id'])) {
                throw new RuntimeException(
                    __("No order id given", "woocommerce-paypal-commerce-gateway")
                );
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

            if ($order->paymentSource() && $order->paymentSource()->card()) {
                $proceed = $this->threedSecure->proceedWithOrder($order);
                if ($proceed === ThreeDSecure::RETRY) {
                    throw new RuntimeException(
                        __(
                            'Something went wrong. Please try again.',
                            'woocommerce-paypal-commerce-gateway'
                        )
                    );
                }
                if ($proceed === ThreeDSecure::REJECT) {
                    throw new RuntimeException(
                        __(
                            'Unfortunatly, we can\'t accept your card. Please choose a different payment method.',
                            'woocommerce-paypal-commerce-gateway'
                        )
                    );
                }
                $this->sessionHandler->replaceOrder($order);
                wp_send_json_success($order);
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
    //phpcs:enable Inpsyde.CodeQuality.FunctionLength.TooLong
}
