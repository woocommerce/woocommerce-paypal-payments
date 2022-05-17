<?php
/**
 * Operations with refund metadata.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Processor
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Processor;

use WC_Order;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Trait RefundMetaTrait.
 */
trait RefundMetaTrait {

	/**
	 * Adds a refund ID to the order metadata.
	 *
	 * @param WC_Order $wc_order The WC order to which metadata will be added.
	 * @param string   $refund_id The refund ID to be added.
	 */
	protected function add_refund_to_meta( WC_Order $wc_order, string $refund_id ): void {
		$refunds   = $this->get_refunds_meta( $wc_order );
		$refunds[] = $refund_id;
		$wc_order->update_meta_data( PayPalGateway::REFUNDS_META_KEY, $refunds );
		$wc_order->save();
	}

	/**
	 * Returns refund IDs from the order metadata.
	 *
	 * @param WC_Order $wc_order The WC order.
	 *
	 * @return string[]
	 */
	protected function get_refunds_meta( WC_Order $wc_order ): array {
		$refunds = $wc_order->get_meta( PayPalGateway::REFUNDS_META_KEY );
		if ( ! is_array( $refunds ) ) {
			$refunds = array();
		}
		return $refunds;
	}
}
