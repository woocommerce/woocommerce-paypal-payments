<?php

/**
 * Builds the Pay with Crypto order-received URL without PayPal's token.
 *
 * @package WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods;

use WC_Order;
/**
 * Redirect target for a Pay with Crypto buyer returning from PayPal.
 */
class PWCOrderReceivedUrl
{
    /**
     * The order's clean order-received URL, dropping `token` and any other
     * request parameters.
     *
     * Null unless the request already carries the order key, so the redirect
     * never hands the key to a caller who did not have it.
     *
     * @param WC_Order              $order  The Pay with Crypto order.
     * @param array<string, string> $params The request's query parameters.
     */
    public function without_token(WC_Order $order, array $params): ?string
    {
        $order_key = (string) $order->get_order_key();
        if ($order_key === '' || !hash_equals($order_key, (string) ($params['key'] ?? ''))) {
            return null;
        }
        return $order->get_checkout_order_received_url();
    }
}
