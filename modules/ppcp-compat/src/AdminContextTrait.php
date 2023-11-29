<?php
/**
 * The compatibility module.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

use WC_Order;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

trait AdminContextTrait {

	/**
	 * Checks if current post id is from a PayPal order.
	 *
	 * @return bool
	 */
	private function is_paypal_order_edit_page(): bool {
		$post_id = wc_clean( wp_unslash( $_GET['id'] ?? $_GET['post'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $post_id ) {
			return false;
		}

		$order = wc_get_order( $post_id );
		if ( ! is_a( $order, WC_Order::class ) ) {
			return false;
		}

		if ( ! $order->get_meta( PayPalGateway::ORDER_ID_META_KEY ) || empty( $order->get_transaction_id() ) ) {
			return false;
		}

		return true;
	}
}
