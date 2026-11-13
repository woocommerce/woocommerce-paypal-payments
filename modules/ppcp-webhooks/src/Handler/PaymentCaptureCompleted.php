<?php

/**
 * Handles the Webhook PAYMENT.CAPTURE.COMPLETED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use Exception;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WP_REST_Response;
/**
 * Class PaymentCaptureCompleted
 */
class PaymentCaptureCompleted implements \WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandler
{
    use TransactionIdHandlingTrait;
    use \WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandlerTrait;
    private LoggerInterface $logger;
    private OrderEndpoint $order_endpoint;
    /**
     * PaymentCaptureCompleted constructor.
     *
     * @param LoggerInterface $logger The logger.
     * @param OrderEndpoint   $order_endpoint The order endpoint.
     */
    public function __construct(LoggerInterface $logger, OrderEndpoint $order_endpoint)
    {
        $this->logger = $logger;
        $this->order_endpoint = $order_endpoint;
    }
    /**
     * The event types a handler handles.
     *
     * @return string[]
     */
    public function event_types(): array
    {
        return array('PAYMENT.CAPTURE.COMPLETED');
    }
    /**
     * Whether a handler is responsible for a given request or not.
     *
     * @param \WP_REST_Request $request The request.
     *
     * @return bool
     */
    public function responsible_for_request(\WP_REST_Request $request): bool
    {
        return in_array($request['event_type'], $this->event_types(), \true);
    }
    /**
     * Responsible for handling the request.
     *
     * @param \WP_REST_Request $request The request.
     *
     * @return WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request): WP_REST_Response
    {
        $webhook_id = (string) ($request['id'] ?? '');
        $resource = $request['resource'];
        if (!is_array($resource)) {
            $message = 'Resource data not found in webhook request.';
            return $this->failure_response($message);
        }
        $wc_order_id = isset($resource['custom_id']) ? (string) $resource['custom_id'] : 0;
        if (!$wc_order_id) {
            $message = sprintf('No order for webhook event %s was found.', $webhook_id);
            return $this->failure_response($message);
        }
        $wc_order = wc_get_order($wc_order_id);
        if (!$wc_order instanceof WC_Order || !$this->is_payment_of_wc_order($request, $wc_order, $this->order_endpoint)) {
            $message = sprintf('No order for webhook event %s was found.', $webhook_id);
            return $this->failure_response($message);
        }
        $paypal_order_id = $this->get_saved_paypal_order_id($wc_order);
        /**
         * Allow access to the webhook logic before updating the WC order.
         *
         * @param WC_Order $wc_order The WC order.
         * @param string   $paypal_order_id The PayPal order ID saved for the WC order.
         */
        do_action('woocommerce_paypal_payments_payment_capture_completed_webhook_handler', $wc_order, $paypal_order_id);
        if (!in_array($wc_order->get_status(), array('pending', 'on-hold'), \true)) {
            return $this->success_response();
        }
        $amount = $resource['amount'] ?? null;
        if (!$this->is_wc_order_total_captured($amount, $wc_order)) {
            return $this->refuse_insufficient_capture($amount, $wc_order, (string) ($resource['id'] ?? ''), $webhook_id);
        }
        $wc_order->add_order_note(__('Payment successfully captured.', 'woocommerce-paypal-payments'));
        $wc_order->payment_complete();
        $wc_order->update_meta_data(AuthorizedPaymentsProcessor::CAPTURED_META_KEY, 'true');
        $wc_order->save();
        $this->logger->info(sprintf('Order %s has been updated through PayPal', (string) $wc_order->get_id()));
        if ($paypal_order_id) {
            try {
                $paypal_order = $this->order_endpoint->order($paypal_order_id);
                $transaction_id = $this->get_paypal_order_transaction_id($paypal_order);
                if ($transaction_id) {
                    $this->update_transaction_id($transaction_id, $wc_order, $this->logger);
                }
            } catch (Exception $exception) {
                $this->logger->warning('Failed to get transaction ID: ' . $exception->getMessage());
            }
        }
        return $this->success_response();
    }
    /**
     * Adds an order note and returns a failure response, leaving the order unpaid.
     *
     * @param mixed $amount The capture amount from the webhook (currency_code and value).
     */
    private function refuse_insufficient_capture($amount, WC_Order $wc_order, string $capture_id, string $event_id): WP_REST_Response
    {
        $value = is_scalar($amount['value'] ?? null) ? (string) $amount['value'] : '';
        $currency = is_scalar($amount['currency_code'] ?? null) ? (string) $amount['currency_code'] : '';
        $wc_order->add_order_note(sprintf(
            /* translators: %1$s is the PayPal capture ID, %2$s the captured amount with its currency. */
            __('PayPal capture %1$s of %2$s does not cover the order total. The order was not marked as paid.', 'woocommerce-paypal-payments'),
            $capture_id,
            trim("{$value} {$currency}")
        ));
        return $this->failure_response(sprintf('Capture in webhook event %1$s does not cover the total of WC order %2$d.', $event_id, $wc_order->get_id()));
    }
}
