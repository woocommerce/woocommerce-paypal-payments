<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;

class PaymentCaptureRefunded implements RequestHandler
{

    private $logger;
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function eventTypes(): array
    {
        return ['PAYMENT.CAPTURE.REFUNDED'];
    }

    public function responsibleForRequest(\WP_REST_Request $request): bool
    {
        return in_array($request['event_type'], $this->eventTypes(), true);
    }

    // phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong
    public function handleRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $response = ['success' => false];
        $orderId = isset($request['resource']['custom_id']) ? (int) $request['resource']['custom_id'] : 0;
        if (! $orderId) {
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

        $wcOrder = wc_get_order($orderId);
        if (! is_a($wcOrder, \WC_Order::class)) {
            $message = sprintf(
            // translators: %s is the PayPal refund Id.
                __('Order for PayPal refund %s not found.', 'woocommerce-paypal-commerce-gateway'),
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

        /**
         * @var \WC_Order $wcOrder
         */
        $refund = wc_create_refund([
            'order_id' => $wcOrder->get_id(),
            'amount' => $request['resource']['amount']['value'],
        ]);
        if (is_wp_error($refund)) {
            $this->logger->log(
                'warning',
                sprintf(
                    // translators: %s is the order id.
                    __('Order %s could not be refunded', 'woocommerce-paypal-commerce-gateway'),
                    (string) $wcOrder->get_id()
                ),
                [
                    'request' => $request,
                    'error' => $refund,
                ]
            );

            $response['message'] = $refund->get_error_message();
            return rest_ensure_response($response);
        }

        $this->logger->log(
            'info',
            sprintf(
                // translators: %1$s is the order id %2$s is the amount which has been refunded.
                __(
                    'Order %1$s has been refunded with %2$s through PayPal',
                    'woocommerce-paypal-commerce-gateway'
                ),
                (string) $wcOrder->get_id(),
                (string) $refund->get_amount()
            ),
            [
                'request' => $request,
                'order' => $wcOrder,
            ]
        );
        $response['success'] = true;
        return rest_ensure_response($response);
    }
    // phpcs:enable Inpsyde.CodeQuality.FunctionLength.TooLong
}
