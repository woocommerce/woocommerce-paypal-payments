<?php

/**
 * Handles the Webhook CHECKOUT.ORDER.COMPLETED
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\CaptureStatus;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PayUponInvoice\PayUponInvoiceGateway;
use WP_REST_Request;
use WP_REST_Response;
/**
 * Class CheckoutOrderCompleted
 */
class CheckoutOrderCompleted implements \WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandler
{
    use \WooCommerce\PayPalCommerce\Webhooks\Handler\RequestHandlerTrait;
    private LoggerInterface $logger;
    /**
     * CheckoutOrderCompleted constructor.
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
        return array('CHECKOUT.ORDER.COMPLETED');
    }
    /**
     * Whether a handler is responsible for a given request or not.
     *
     * @param WP_REST_Request $request The request.
     *
     * @return bool
     */
    public function responsible_for_request(WP_REST_Request $request): bool
    {
        return in_array($request['event_type'], $this->event_types(), \true);
    }
    /**
     * Responsible for handling the request.
     *
     * @param WP_REST_Request $request The request.
     *
     * @return WP_REST_Response
     */
    public function handle_request(WP_REST_Request $request): WP_REST_Response
    {
        $custom_ids = $this->get_wc_order_ids_from_request($request);
        if (empty($custom_ids)) {
            return $this->no_custom_ids_response($request);
        }
        $wc_orders = $this->get_wc_orders_from_custom_ids($custom_ids, $this->get_paypal_order_id_from_request($request));
        if (!$wc_orders) {
            return $this->no_wc_orders_response($request);
        }
        $paypal_order = $request['resource'];
        $paypal_order_id = (string) ($paypal_order['id'] ?? '');
        $purchase_units = (array) ($paypal_order['purchase_units'] ?? array());
        foreach ($wc_orders as $wc_order) {
            if (PayUponInvoiceGateway::ID === $wc_order->get_payment_method()) {
                continue;
            }
            if (!$this->has_completed_capture_of_total($purchase_units, $wc_order)) {
                // Only orders still awaiting payment need the note; repeat events for paid, on-hold or refunded orders do not.
                if ($wc_order->needs_payment()) {
                    $this->record_missing_capture($wc_order, $paypal_order_id);
                }
                continue;
            }
            $wc_order->payment_complete();
            $this->logger->info(sprintf('Order %s has been updated through PayPal', (string) $wc_order->get_id()));
        }
        return $this->success_response();
    }
    /**
     * A COMPLETED PayPal order status does not prove the total was captured: the order can
     * hold only an authorization, a pending or declined capture, or a capture of a patched amount.
     *
     * @param array $purchase_units The purchase units of the PayPal order from the webhook.
     */
    private function has_completed_capture_of_total(array $purchase_units, WC_Order $wc_order): bool
    {
        foreach ($purchase_units as $purchase_unit) {
            if (!is_array($purchase_unit)) {
                continue;
            }
            $custom_id = (string) ($purchase_unit['custom_id'] ?? '');
            if ($custom_id === (string) $wc_order->get_id() && $this->purchase_unit_has_completed_capture_of_total($purchase_unit, $wc_order)) {
                return \true;
            }
        }
        return \false;
    }
    private function purchase_unit_has_completed_capture_of_total(array $purchase_unit, WC_Order $wc_order): bool
    {
        foreach ((array) ($purchase_unit['payments']['captures'] ?? array()) as $capture) {
            if (!is_array($capture) || ($capture['status'] ?? '') !== CaptureStatus::COMPLETED) {
                continue;
            }
            if ($this->is_wc_order_total_captured($capture['amount'] ?? null, $wc_order)) {
                return \true;
            }
        }
        return \false;
    }
    private function record_missing_capture(WC_Order $wc_order, string $paypal_order_id): void
    {
        $wc_order->add_order_note(sprintf(
            /* translators: %s is the PayPal order ID. */
            __('PayPal order %s has no completed capture of the order total. The order was not marked as paid.', 'woocommerce-paypal-payments'),
            $paypal_order_id
        ));
        $this->logger->warning(sprintf('WC order %1$d was not marked as paid: PayPal order %2$s has no completed capture of its total.', $wc_order->get_id(), $paypal_order_id));
    }
}
