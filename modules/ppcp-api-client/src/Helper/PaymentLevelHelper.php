<?php
/**
 * Helper for building Level 2/3 card processing data from WooCommerce orders.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WC_Order;

class PaymentLevelHelper {

	/**
	 * Builds supplementary card data from WooCommerce order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @param string   $level The processing level ('level_2' or 'level_3').
	 * @return array{
	 *     supplementary_data: array{
	 *         card: array{
	 *             level_2?: array{
	 *                 customer_reference: string,
	 *                 tax_total: array{
	 *                     currency_code: string,
	 *                     value: string
	 *                 }
	 *             }
	 *         }
	 *     }
	 * }|null Supplementary data array ready for PurchaseUnit, or null if no data could be built.
	 */
	public function build( WC_Order $order, string $level ): ?array {
		$data = array(
			'supplementary_data' => array(
				'card' => array(),
			),
		);

		if ( 'level_2' === $level ) {
			$level_2_data = $this->build_level_2( $order );
			if ( $level_2_data ) {
				$data['supplementary_data']['card']['level_2'] = $level_2_data;
			}
		}

		/* phpcs:disable Squiz.PHP.CommentedOutCode.Found
			Future: level_3 support
			if ( 'level_3' === $level ) {
				$level_3_data = $this->build_level_3( $order );
				if ( $level_3_data ) {
					$data['supplementary_data']['card']['level_3'] = $level_3_data;
				}
			}
		*/

		return ! empty( $data['supplementary_data']['card'] ) ? $data : null;
	}

	/**
	 * Builds Level 2 card data.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return array{
	 *     customer_reference: string,
	 *     tax_total: array{
	 *         currency_code: string,
	 *         value: string
	 *     }
	 * } Level 2 data array.
	 */
	private function build_level_2( WC_Order $order ): array {
		/**
		 * Filters the Level 2 customer reference/PO number.
		 *
		 * @param string   $customer_reference The customer reference (default: order ID).
		 * @param WC_Order $order              The WooCommerce order.
		 */
		$customer_reference = apply_filters(
			'woocommerce_paypal_payments_level2_customer_reference',
			(string) $order->get_id(),
			$order
		);

		return array(
			'customer_reference' => substr( $customer_reference, 0, 17 ),
			'tax_total'          => array(
				'currency_code' => $order->get_currency(),
				'value'         => number_format( (float) $order->get_total_tax(), 2, '.', '' ),
			),
		);
	}
}
