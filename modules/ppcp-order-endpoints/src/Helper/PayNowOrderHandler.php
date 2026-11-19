<?php

/**
 * Creates and pays the WC order of an express "Pay Now" approval.
 *
 * @package WooCommerce\PayPalCommerce\OrderEndpoints\Helper
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderEndpoints\Helper;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
class PayNowOrderHandler
{
    private \WooCommerce\PayPalCommerce\OrderEndpoints\Helper\WooCommerceOrderCreator $wc_order_creator;
    private PayPalGateway $gateway;
    private SettingsProvider $settings_provider;
    private Context $context;
    public function __construct(\WooCommerce\PayPalCommerce\OrderEndpoints\Helper\WooCommerceOrderCreator $wc_order_creator, PayPalGateway $gateway, SettingsProvider $settings_provider, Context $context)
    {
        $this->wc_order_creator = $wc_order_creator;
        $this->gateway = $gateway;
        $this->settings_provider = $settings_provider;
        $this->context = $context;
    }
    /**
     * Whether the approval completes the purchase here, without the checkout form.
     *
     * @param array $request_data The approval request; the client asks with `should_create_wc_order`.
     */
    public function applies(array $request_data): bool
    {
        return ($request_data['should_create_wc_order'] ?? \false) && $this->settings_provider->enable_pay_now() && !$this->context->is_checkout();
    }
    /**
     * Creates the WC order from the cart and pays it.
     *
     * @param Order      $order       The approved PayPal order.
     * @param array|null $paypal_data Payer and shipping data from the buyer's request.
     * @return string Where to send the buyer: order-received, or the gateway's
     *                                redirect after a retry or a failure.
     */
    public function pay(Order $order, ?array $paypal_data = null): string
    {
        $cart = WC()->cart;
        $wc_order = $this->wc_order_creator->create_from_paypal_order($order, $cart, $paypal_data);
        $payment_result = $this->gateway->process_payment($wc_order->get_id());
        // Re-fetch order, because process_payment() does not update our initial copy.
        $paid_order = wc_get_order($wc_order->get_id());
        if ($paid_order instanceof WC_Order) {
            // Not the payment result: a retry also returns success, for an unpaid order.
            // Not is_paid(): an authorized order is on-hold. Same rule as wc_clear_cart_after_payment().
            $was_processed = !$paid_order->has_status(array('pending', 'failed', 'cancelled'));
            // The order-received page empties the cart, but the buyer may never reach it.
            if ($was_processed) {
                $cart->empty_cart();
            }
        }
        return $payment_result['redirect'];
    }
}
