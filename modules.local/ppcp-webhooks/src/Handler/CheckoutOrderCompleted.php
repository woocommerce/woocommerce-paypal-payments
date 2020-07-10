<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;

class CheckoutOrderCompleted implements RequestHandler
{

    private $logger;
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function eventTypes(): array
    {
        return ['CHECKOUT.ORDER.COMPLETED'];
    }

    public function responsibleForRequest(\WP_REST_Request $request): bool
    {
        return in_array($request['event_type'], $this->eventTypes(), true);
    }

    // phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong
    public function handleRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $response = ['success' => false];
        $orderIds = array_filter(
            array_map(
                static function (array $purchaseUnit): string {
                    return isset($purchaseUnit['custom_id']) ? (string) $purchaseUnit['custom_id'] : '';
                },
                isset($request['resource']['purchase_units']) ?
                    (array) $request['resource']['purchase_units'] : []
            ),
            static function (string $orderId): bool {
                return ! empty($orderId);
            }
        );

        if (empty($orderIds)) {
            $message = sprintf(
                // translators: %s is the PayPal webhook Id.
                __('No order for webhook event %s was found.', 'woocommerce-paypal-commerce-gateway'),
                isset($request['id']) ? $request['id'] : ''
            );
            $this->logger->log(
                'warning',
                $message,
                [
                    'request' => $request,
                ]
            );
            $response['message'] = $message;
            return rest_ensure_response($response);
        }

        $args = [
            'post__in' => $orderIds,
            'limit' => -1,
        ];
        $wcOrders = wc_get_orders($args);
        if (! $wcOrders) {
            $message = sprintf(
            // translators: %s is the PayPal order Id.
                __('Order for PayPal order %s not found.', 'woocommerce-paypal-commerce-gateway'),
                isset($request['resource']['id']) ? $request['resource']['id'] : ''
            );
            $this->logger->log(
                'warning',
                $message,
                [
                    'request' => $request,
                ]
            );
            $response['message'] = $message;
            return rest_ensure_response($response);
        }

        foreach ($wcOrders as $wcOrder) {
            if ($wcOrder->get_status() !== 'on-hold') {
                continue;
            }
            /**
             * @var \WC_Order $wcOrder
             */
            $wcOrder->update_status(
                'processing',
                __('Payment received.', 'woocommerce-paypal-commerce-gateway')
            );
            $this->logger->log(
                'info',
                sprintf(
                    // translators: %s is the order ID.
                    __('Order %s has been updated through PayPal', 'woocommerce-paypal-commerce-gateway'),
                    (string) $wcOrder->get_id()
                ),
                [
                    'request' => $request,
                    'order' => $wcOrder,
                ]
            );
        }
        $response['success'] = true;
        return rest_ensure_response($response);
    }
    // phpcs:enable Inpsyde.CodeQuality.FunctionLength.TooLong
}
