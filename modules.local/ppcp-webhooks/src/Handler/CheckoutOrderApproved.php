<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks\Handler;

use Inpsyde\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use Inpsyde\PayPalCommerce\ApiClient\Exception\RuntimeException;
use Psr\Log\LoggerInterface;

class CheckoutOrderApproved implements RequestHandler
{
    use PrefixTrait;

    private $logger;
    private $orderEndpoint;

    public function __construct(LoggerInterface $logger, string $prefix, OrderEndpoint $orderEndpoint)
    {
        $this->logger = $logger;
        $this->prefix = $prefix;
        $this->orderEndpoint = $orderEndpoint;
    }

    public function eventTypes(): array
    {
        return [
            'CHECKOUT.ORDER.APPROVED',
        ];
    }

    public function responsibleForRequest(\WP_REST_Request $request): bool
    {
        return in_array($request['event_type'], $this->eventTypes(), true);
    }

    //phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong
    public function handleRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $response = ['success' => false];
        $customIds = array_filter(
            array_map(
                static function (array $purchaseUnit): string {
                    return isset($purchaseUnit['custom_id']) ?
                        (string) $purchaseUnit['custom_id'] : '';
                },
                isset($request['resource']['purchase_units']) ?
                    (array) $request['resource']['purchase_units'] : []
            ),
            static function (string $orderId): bool {
                return ! empty($orderId);
            }
        );

        if (empty($customIds)) {
            $message = sprintf(
            // translators: %s is the PayPal webhook Id.
                __(
                    'No order for webhook event %s was found.',
                    'woocommerce-paypal-commerce-gateway'
                ),
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

        try {
            $order = isset($request['resource']['id']) ?
                $this->orderEndpoint->order($request['resource']['id']) : null;
            if (! $order) {
                $message = sprintf(
                // translators: %s is the PayPal webhook Id.
                    __(
                        'No paypal payment for webhook event %s was found.',
                        'woocommerce-paypal-commerce-gateway'
                    ),
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

            if ($order->intent() === 'CAPTURE') {
                    $this->orderEndpoint->capture($order);
            }
        } catch (RuntimeException $error) {
            $message = sprintf(
            // translators: %s is the PayPal webhook Id.
                __(
                    'Could not capture payment for webhook event %s.',
                    'woocommerce-paypal-commerce-gateway'
                ),
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

        $wcOrderIds = array_map(
            [
                $this,
                'sanitizeCustomId',
            ],
            $customIds
        );
        $args = [
            'post__in' => $wcOrderIds,
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

        $newStatus = $order->intent() === 'CAPTURE' ? 'processing' : 'on-hold';
        $statusMessage = $order->intent() === 'CAPTURE' ?
            __('Payment received.', 'woocommerce-paypal-commerce-gateway')
            :  __('Payment can be captured.', 'woocommerce-paypal-commerce-gateway');
        foreach ($wcOrders as $wcOrder) {
            if (! in_array($wcOrder->get_status(), ['pending', 'on-hold'], true)) {
                continue;
            }
            /**
             * @var \WC_Order $wcOrder
             */
            $wcOrder->update_status(
                $newStatus,
                $statusMessage
            );
            $this->logger->log(
                'info',
                sprintf(
                // translators: %s is the order ID.
                    __(
                        'Order %s has been updated through PayPal',
                        'woocommerce-paypal-commerce-gateway'
                    ),
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
    //phpcs:enable Inpsyde.CodeQuality.FunctionLength.TooLong
}
