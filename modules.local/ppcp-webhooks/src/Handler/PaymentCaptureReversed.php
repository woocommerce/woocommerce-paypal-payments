<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks\Handler;

use Psr\Log\LoggerInterface;

class PaymentCaptureReversed implements RequestHandler
{

    use PrefixTrait;
    private $logger;
    public function __construct(LoggerInterface $logger, string $prefix)
    {
        $this->logger = $logger;
        $this->prefix = $prefix;
    }

    public function eventTypes(): array
    {
        return [
            'PAYMENT.CAPTURE.REVERSED',
            'PAYMENT.ORDER.CANCELLED',
            'PAYMENT.CAPTURE.DENIED',
        ];
    }

    public function responsibleForRequest(\WP_REST_Request $request): bool
    {
        return in_array($request['event_type'], $this->eventTypes(), true);
    }

    // phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong
    public function handleRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $response = ['success' => false];
        $orderId = isset($request['resource']['custom_id']) ? $this->sanitizeCustomId($request['resource']['custom_id']) : 0;
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
        $response['success'] = (bool) $wcOrder->update_status('cancelled');

        $message = $response['success'] ? sprintf(
            // translators: %1$s is the order id.
            __('Order %1$s has been cancelled through PayPal', 'woocommerce-paypal-commerce-gateway'),
            (string) $wcOrder->get_id()
        ) : sprintf(
            // translators: %1$s is the order id.
            __('Failed to cancel order %1$s through PayPal', 'woocommerce-paypal-commerce-gateway'),
            (string) $wcOrder->get_id()
        );
        $this->logger->log(
            $response['success'] ? 'info' : 'warning',
            $message,
            [
                'request' => $request,
                'order' => $wcOrder,
            ]
        );
        return rest_ensure_response($response);
    }
    // phpcs:enable Inpsyde.CodeQuality.FunctionLength.TooLong
}
