<?php

/**
 * Remembers a settled PayPal return in the WC session, so a repeated return in
 * the same session can be matched to its order.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use WC_Order;
use WC_Session_Handler;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
class CompletedReturnSession
{
    private const SESSION_KEY = 'ppcp_completed_return';
    private const LIFETIME = 5 * MINUTE_IN_SECONDS;
    public function remember(string $paypal_order_id, int $wc_order_id): void
    {
        $wc_order = wc_get_order($wc_order_id);
        if (!$wc_order instanceof WC_Order) {
            return;
        }
        // On-hold means PayPal authorized the payment, so it counts as settled.
        $settled_statuses = array_merge((array) wc_get_is_paid_statuses(), array('on-hold'));
        if (!$wc_order->has_status($settled_statuses)) {
            return;
        }
        if ($wc_order->get_meta(PayPalGateway::ORDER_ID_META_KEY) !== $paypal_order_id) {
            return;
        }
        $wc_session = WC()->session ?? null;
        if (!$wc_session instanceof WC_Session_Handler) {
            return;
        }
        // A return in another browser has no session cookie yet, and WC saves no session data without one.
        if (!$wc_session->has_session()) {
            $wc_session->set_customer_session_cookie(\true);
        }
        $wc_session->set(self::SESSION_KEY, array('paypal_order_id' => $paypal_order_id, 'wc_order_id' => $wc_order_id, 'expires' => time() + self::LIFETIME));
    }
    public function remembered_order_for(string $paypal_order_id): ?WC_Order
    {
        $wc_session = WC()->session ?? null;
        if (!$wc_session) {
            return null;
        }
        $stored_return = $wc_session->get(self::SESSION_KEY);
        if (!is_array($stored_return)) {
            return null;
        }
        $remembered_paypal_order_id = $stored_return['paypal_order_id'] ?? '';
        $expires_at = (int) ($stored_return['expires'] ?? 0);
        if (!is_string($remembered_paypal_order_id) || $remembered_paypal_order_id !== $paypal_order_id || $expires_at < time()) {
            return null;
        }
        $wc_order = wc_get_order((int) ($stored_return['wc_order_id'] ?? 0));
        return $wc_order instanceof WC_Order ? $wc_order : null;
    }
}
