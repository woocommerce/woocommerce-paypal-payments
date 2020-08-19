<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Webhooks\Handler;

use Inpsyde\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use Psr\Log\LoggerInterface;

class PaymentCaptureCompleted implements RequestHandler
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
        return ['PAYMENT.CAPTURE.COMPLETED'];
    }

    public function responsibleForRequest(\WP_REST_Request $request): bool
    {
        return in_array($request['event_type'], $this->eventTypes(), true);
    }

    // phpcs:disable Inpsyde.CodeQuality.FunctionLength.TooLong
    public function handleRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $response = ['success' => false];
        $orderId = isset($request['resource']['custom_id']) ?
            $this->sanitizeCustomId($request['resource']['custom_id']) : 0;
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

        if ($wcOrder->get_status() !== 'on-hold') {
            $response['success'] = true;
            return rest_ensure_response($response);
        }
        $wcOrder->add_order_note(
            __('Payment successfully captured.', 'woocommerce-paypal-commerce-gateway')
        );

        $wcOrder->set_status('processing');
        $wcOrder->update_meta_data(PayPalGateway::CAPTURED_META_KEY, 'true');
        $wcOrder->save();
        $this->logger->log(
            'info',
            sprintf(
            // translators: %s is the order ID.
                __('Order %s has been updated through PayPal', 'woocommerce-paypal-commerce-gateway'),
                (string)$wcOrder->get_id()
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
