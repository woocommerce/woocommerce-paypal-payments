<?php

/**
 * Trait which helps to handle the request.
 *
 * @package WooCommerce\PayPalCommerce\Webhooks\Handler
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Webhooks\Handler;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\Webhooks\CustomIds;
use WP_REST_Request;
use WP_REST_Response;
trait RequestHandlerTrait
{
    /**
     * Get available custom ids from the given request
     *
     * @param WP_REST_Request $request The request.
     * @return string[]
     */
    protected function get_custom_ids_from_request(WP_REST_Request $request): array
    {
        $resource = $request['resource'];
        if (!is_array($resource)) {
            return array();
        }
        $ids = array();
        if (isset($resource['custom_id']) && !empty($resource['custom_id'])) {
            $ids[] = $resource['custom_id'];
        } elseif (isset($resource['purchase_units'])) {
            $ids = array_map(static function (array $purchase_unit): string {
                return $purchase_unit['custom_id'] ?? '';
            }, (array) $resource['purchase_units']);
        }
        return array_values(array_filter($ids, function (string $id): bool {
            return !empty($id);
        }));
    }
    /**
     * Get available WC order ids from the given request.
     *
     * @param WP_REST_Request $request The request.
     * @return string[]
     */
    protected function get_wc_order_ids_from_request(WP_REST_Request $request): array
    {
        $ids = $this->get_custom_ids_from_request($request);
        return array_values(array_filter($ids, function (string $id): bool {
            return strpos($id, CustomIds::CUSTOMER_ID_PREFIX) === \false;
        }));
    }
    /**
     * Get available WC customer ids from the given request.
     *
     * @param WP_REST_Request $request The request.
     * @return string[]
     */
    protected function get_wc_customer_ids_from_request(WP_REST_Request $request): array
    {
        $ids = $this->get_custom_ids_from_request($request);
        $customer_ids = array_values(array_filter($ids, function (string $id): bool {
            return strpos($id, CustomIds::CUSTOMER_ID_PREFIX) === 0;
        }));
        return array_map(function (string $str): string {
            return (string) substr($str, strlen(CustomIds::CUSTOMER_ID_PREFIX));
        }, $customer_ids);
    }
    /**
     * Get the ID of the PayPal order the given request is about.
     *
     * @param WP_REST_Request $request The request.
     * @return string Empty if the resource does not carry it (e.g. a refund).
     */
    protected function get_paypal_order_id_from_request(WP_REST_Request $request): string
    {
        $resource = $request['resource'];
        if (!is_array($resource)) {
            return '';
        }
        if (isset($resource['purchase_units'])) {
            // CHECKOUT.PAYMENT-APPROVAL.REVERSED carries the ID as order_id.
            return (string) ($resource['order_id'] ?? $resource['id'] ?? '');
        }
        return (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? '');
    }
    /**
     * Get the ID of the resource's parent from its 'up' link: the PayPal order, or the captured or authorized payment.
     */
    protected function get_parent_id_from_request(WP_REST_Request $request): string
    {
        $parent_id = '';
        foreach ((array) ($request['resource']['links'] ?? array()) as $link) {
            if ('up' === ($link['rel'] ?? '')) {
                $parent_id = basename((string) ($link['href'] ?? ''));
            }
        }
        return $parent_id;
    }
    /**
     * Get WC orders from the given custom ids.
     *
     * Only orders whose saved PayPal order ID equals $paypal_order_id are returned.
     *
     * @param array  $custom_ids The custom ids.
     * @param string $paypal_order_id The ID of the PayPal order carrying the custom ids.
     * @return WC_Order[]
     */
    protected function get_wc_orders_from_custom_ids(array $custom_ids, string $paypal_order_id): array
    {
        $order_ids = $custom_ids;
        $args = array('post__in' => $order_ids, 'type' => 'shop_order', 'limit' => -1);
        $orders = wc_get_orders($args);
        if (!is_array($orders)) {
            return array();
        }
        return array_values(array_filter($orders, function (WC_Order $wc_order) use ($paypal_order_id): bool {
            return $this->is_paypal_order_of_wc_order($paypal_order_id, $wc_order);
        }));
    }
    /**
     * Checks whether the given PayPal order is the one saved for the WC order.
     *
     * A custom_id alone is not enough: other PayPal orders on the same account can carry any custom_id.
     *
     * @param string   $paypal_order_id The PayPal order ID from the webhook event.
     * @param WC_Order $wc_order The WC order found via the custom_id.
     */
    protected function is_paypal_order_of_wc_order(string $paypal_order_id, WC_Order $wc_order): bool
    {
        return $paypal_order_id && $this->get_saved_paypal_order_id($wc_order) === $paypal_order_id;
    }
    /**
     * Agentic commerce orders can have the PayPal order ID only in `_paypal_order_id`.
     */
    protected function get_saved_paypal_order_id(WC_Order $wc_order): string
    {
        $paypal_order_id = (string) $wc_order->get_meta(PayPalGateway::ORDER_ID_META_KEY);
        if (!$paypal_order_id && $wc_order->get_meta('_agentic_commerce')) {
            $paypal_order_id = (string) $wc_order->get_meta('_paypal_order_id');
        }
        return $paypal_order_id;
    }
    /**
     * Checks whether the payment (capture, authorization, refund) in the given request
     * belongs to the PayPal order saved for the WC order.
     *
     * @param WP_REST_Request $request The request.
     * @param WC_Order        $wc_order The WC order found via the custom_id.
     * @param OrderEndpoint   $order_endpoint The order endpoint.
     */
    protected function is_payment_of_wc_order(WP_REST_Request $request, WC_Order $wc_order, OrderEndpoint $order_endpoint): bool
    {
        $paypal_order_id = $this->get_paypal_order_id_from_request($request);
        if ($paypal_order_id) {
            return $this->is_paypal_order_of_wc_order($paypal_order_id, $wc_order);
        }
        $parent_id = $this->get_parent_id_from_request($request);
        $saved_paypal_order_id = $this->get_saved_paypal_order_id($wc_order);
        if (!$parent_id || !$saved_paypal_order_id) {
            return \false;
        }
        if ($parent_id === $saved_paypal_order_id) {
            return \true;
        }
        foreach ($order_endpoint->order($saved_paypal_order_id)->purchase_units() as $purchase_unit) {
            $payments = $purchase_unit->payments();
            if (!$payments) {
                continue;
            }
            foreach (array_merge($payments->captures(), $payments->authorizations()) as $payment) {
                if ($payment->id() === $parent_id) {
                    return \true;
                }
            }
        }
        return \false;
    }
    /**
     * A capture naming the WC order in custom_id does not imply the total was paid:
     * the buyer can patch the PayPal order in the browser before capturing it, and an
     * authorization can be captured in parts.
     *
     * "At least the total" because AmountFactory sends 1.00 for free-trial card orders.
     *
     * @param mixed    $amount The capture amount from the webhook (currency_code and value).
     * @param WC_Order $wc_order The WC order.
     */
    protected function is_wc_order_total_captured($amount, WC_Order $wc_order): bool
    {
        if (!is_array($amount) || !is_numeric($amount['value'] ?? null) || !is_string($amount['currency_code'] ?? null)) {
            return \false;
        }
        $captured = new Money((float) $amount['value'], (string) $amount['currency_code']);
        $total = new Money((float) $wc_order->get_total(), $wc_order->get_currency());
        return $captured->currency_code() === $total->currency_code() && (float) $captured->value_str() >= (float) $total->value_str();
    }
    /**
     * Logs and returns response for no custom ids found in request.
     *
     * @param WP_REST_Request $request The request.
     * @return WP_REST_Response
     */
    protected function no_custom_ids_response(WP_REST_Request $request): WP_REST_Response
    {
        $message = sprintf(
            'WC order ID was not found in webhook event %s for PayPal order %s.',
            (string) ($request['id'] ?? ''),
            // Psalm 4.x does not seem to understand ?? with ArrayAccess correctly.
            $request['resource'] !== null && isset($request['resource']['id']) ? $request['resource']['id'] : ''
        );
        return $this->failure_response($message);
    }
    /**
     * Logs and returns response for no WC orders found via custom ids.
     *
     * @param WP_REST_Request $request The request.
     * @return WP_REST_Response
     */
    protected function no_wc_orders_response(WP_REST_Request $request): WP_REST_Response
    {
        $message = sprintf('WC order %s not found in webhook event %s for PayPal order %s.', implode(', ', $this->get_custom_ids_from_request($request)), (string) ($request['id'] ?? ''), $request['resource'] !== null && isset($request['resource']['id']) ? $request['resource']['id'] : '');
        return $this->failure_response($message);
    }
    /**
     * Returns success response.
     *
     * @return WP_REST_Response
     */
    protected function success_response(): WP_REST_Response
    {
        return new WP_REST_Response(array('success' => \true));
    }
    /**
     * Logs and returns failure response with the given message.
     *
     * @param string $message The message.
     * @return WP_REST_Response
     */
    private function failure_response(string $message = ''): WP_REST_Response
    {
        $response = array('success' => \false);
        if ($message) {
            $this->logger->warning($message);
            $response['message'] = $message;
        }
        return new WP_REST_Response($response);
    }
}
