<?php

/**
 * The order tracking module.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderTracking;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\AuthorizedPaymentsProcessor;
trait TrackingAvailabilityTrait
{
    /**
     * Checks if tracking should be enabled for current post.
     *
     * @param Bearer $bearer The Bearer.
     * @return bool
     */
    protected function is_tracking_enabled(Bearer $bearer): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $post_id = (int) wc_clean(wp_unslash($_GET['id'] ?? $_GET['post'] ?? ''));
        if (!$post_id) {
            return \false;
        }
        $order = wc_get_order($post_id);
        if (!is_a($order, WC_Order::class)) {
            return \false;
        }
        $captured = $order->get_meta(AuthorizedPaymentsProcessor::CAPTURED_META_KEY);
        $is_captured = empty($captured) || wc_string_to_bool($captured);
        $is_paypal_order_edit_page = $order->get_meta(PayPalGateway::ORDER_ID_META_KEY) && !empty($order->get_transaction_id());
        try {
            $token = $bearer->bearer();
            return $is_paypal_order_edit_page && $is_captured && $token->is_tracking_available() && apply_filters('woocommerce_paypal_payments_shipment_tracking_enabled', \true);
        } catch (RuntimeException $exception) {
            return \false;
        }
    }
}
