<?php
/**
 * Creates and pays the WC order of an express "Pay Now" approval.
 *
 * @package WooCommerce\PayPalCommerce\OrderEndpoints\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\OrderEndpoints\Helper;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

class PayNowOrderHandler {

	private WooCommerceOrderCreator $wc_order_creator;

	private PayPalGateway $gateway;

	public function __construct( WooCommerceOrderCreator $wc_order_creator, PayPalGateway $gateway ) {
		$this->wc_order_creator = $wc_order_creator;
		$this->gateway          = $gateway;
	}

	/**
	 * Creates the WC order from the cart and pays it.
	 *
	 * @param Order      $order       The approved PayPal order.
	 * @param array|null $paypal_data Payer and shipping data from the buyer's request.
	 * @return string Where to send the buyer: order-received, or the gateway's
	 *                                redirect after a retry or a failure.
	 */
	public function pay( Order $order, ?array $paypal_data = null ): string {
		$cart           = WC()->cart;
		$wc_order       = $this->wc_order_creator->create_from_paypal_order( $order, $cart, $paypal_data );
		$payment_result = $this->gateway->process_payment( $wc_order->get_id() );

		// The order-received page empties the cart, but the buyer may never reach it.
		// Not the payment result: a retry also returns success, for an unpaid order.
		// Reloaded, because process_payment() updates its own copy of the order.
		$paid_order = wc_get_order( $wc_order->get_id() );
		if ( $paid_order instanceof WC_Order && $paid_order->is_paid() ) {
			$cart->empty_cart();
		}

		return $payment_result['redirect'];
	}
}
