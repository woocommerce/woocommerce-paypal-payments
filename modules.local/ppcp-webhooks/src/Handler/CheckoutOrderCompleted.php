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

    public function eventType(): string
    {
        return 'CHECKOUT.ORDER.COMPLETED';
    }

    public function responsibleForRequest(\WP_REST_Request $request): bool
    {
        return $request['event_type'] === $this->eventType();
    }

    public function handleRequest(\WP_REST_Request $request): \WP_REST_Response
    {
        $response = ['success' => false];
        $orderIds = array_filter(
            array_map(
                function(array $purchaseUnit) : string {
                    return isset($purchaseUnit['custom_id']) ? (string) $purchaseUnit['custom_id'] : '';
                },
                isset($request['resource']['purchase_units']) ? (array) $request['resource']['purchase_units'] : []
            ),
            function(string $orderId) : bool {
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
            return rest_ensure_response(new \WP_Error($message));
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
            return rest_ensure_response(new \WP_Error($message));
        }

        foreach ($wcOrders as $wcOrder) {
            if ($wcOrder->get_status() !== 'on-hold') {
                continue;
            }
            /**
             * @var \WC_Product $wcOrder
             */
            $wcOrder->update_status(
                'processing',
                __('Payment received.', 'woocommerce-paypal-gateway')
            );
            $this->logger->log(
                'info',
                __('Order ' . $wcOrder->get_id() . ' has been updated through PayPal' , 'woocommerce-paypal-gateway'),
                [
                    'request' => $request,
                    'order' => $wcOrder,
                ]
            );
        }
        $response['success'] = true;
        return rest_ensure_response($response);
    }
}