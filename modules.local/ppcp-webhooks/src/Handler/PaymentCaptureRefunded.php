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

    public function eventType(): string
    {
        return 'PAYMENT.CAPTURE.REFUNDED';
    }

    public function responsibleForRequest(\WP_REST_Request $request): bool
    {
        return $request['event_type'] === $this->eventType();
    }

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
        $wcOrder->update_status(
            'refunded',
            __('Payment Refunded.', 'woocommerce-paypal-gateway')
        );
        $this->logger->log(
            'info',
            __('Order ' . $wcOrder->get_id() . ' has been updated through PayPal' , 'woocommerce-paypal-gateway'),
            [
                'request' => $request,
                'order' => $wcOrder,
            ]
        );
        $response['success'] = true;
        return rest_ensure_response($response);
    }
}