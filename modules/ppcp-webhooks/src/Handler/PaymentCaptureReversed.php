<?php

/**
 * Handles the following Webhooks:
 * - PAYMENT.CAPTURE.REVERSED
 * - PAYMENT.ORDER.CANCELLED
 * - PAYMENT.CAPTURE.DENIED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
/**
 * Class PaymentCaptureReversed
 */
class PaymentCaptureReversed implements \WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandler
{
    use \WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandlerTrait;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * PaymentCaptureReversed constructor.
     *
     * @param LoggerInterface $logger The logger.
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    /**
     * The event types a handler handles.
     *
     * @return string[]
     */
    public function event_types(): array
    {
        return array('PAYMENT.CAPTURE.REVERSED', 'PAYMENT.ORDER.CANCELLED', 'PAYMENT.CAPTURE.DENIED');
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
     * @return \WP_REST_Response
     */
    public function handle_request(\WP_REST_Request $request): \WP_REST_Response
    {
        $order_id = isset($request['resource']['custom_id']) ? $request['resource']['custom_id'] : 0;
        if (!$order_id) {
            $message = sprintf('No order for webhook event %s was found.', isset($request['id']) ? $request['id'] : '');
            return $this->failure_response($message);
        }
        $wc_order = wc_get_order($order_id);
        if (!is_a($wc_order, \WC_Order::class)) {
            $message = sprintf('Order for PayPal refund %s not found.', isset($request['resource']['id']) ? $request['resource']['id'] : '');
            return $this->failure_response($message);
        }
        /**
         * Allows adding an update status note.
         */
        $note = apply_filters('ppcp_payment_capture_reversed_webhook_update_status_note', '', $wc_order, $request['event_type']);
        $is_success = $wc_order->update_status('cancelled', $note);
        if (!$is_success) {
            $message = sprintf('Failed to cancel order %1$s cancelled through PayPal', (string) $wc_order->get_id());
            return $this->failure_response($message);
        }
        $message = sprintf('Order %1$s has been cancelled through PayPal', (string) $wc_order->get_id());
        $this->logger->info($message);
        return $this->success_response();
    }
}
