<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WC_Order;

class OrderStatusHelper {

	/**
	 * @return string[]
	 */
	public function get_awaiting_payment_statuses( WC_Order $wc_order ): array {
		/**
		 * Filters the WC order statuses considered as "awaiting payment".
		 *
		 * @param string[] $statuses Default: array( 'pending', 'on-hold', 'failed' ).
		 * @param WC_Order $wc_order The WooCommerce order.
		 */
		return (array) apply_filters(
			'woocommerce_paypal_payments_awaiting_payment_statuses',
			array( 'pending', 'on-hold', 'failed' ),
			$wc_order
		);
	}

	public function is_awaiting_payment( WC_Order $wc_order ): bool {
		return in_array( $wc_order->get_status(), $this->get_awaiting_payment_statuses( $wc_order ), true );
	}
}
