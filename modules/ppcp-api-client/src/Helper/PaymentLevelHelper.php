<?php
/**
 * Helper for building Level 2/3 card processing data.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Amount;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;

class PaymentLevelHelper {

	/**
	 * Builds supplementary card data.
	 *
	 * @param Amount $amount The Amount object based off a WooCommerce cart.
	 * @param string $level The processing level ('level_2' or 'level_3').
	 * @return array{
	 *     supplementary_data: array{
	 *         card: array{
	 *             level_2?: array{
	 *                 invoice_id: string,
	 *                 tax_total?: array{
	 *                     currency_code: string,
	 *                     value: string
	 *                 }
	 *             }
	 *         }
	 *     }
	 * }|null Supplementary data array ready for PurchaseUnit, or null if no data could be built.
	 */
	public function build( Amount $amount, string $level ): ?array {
		$data = array(
			'supplementary_data' => array(
				'card' => array(),
			),
		);

		if ( 'level_2' === $level ) {
			$breakdown = $amount->breakdown();
			$tax_total = $breakdown ? $breakdown->tax_total() : null;

			$data['supplementary_data']['card']['level_2'] = $this->build_level_2( $tax_total );
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
	 * @param Money|null $tax_total The tax total amount.
	 * @return array{
	 *     invoice_id: string,
	 *     tax_total?: array{
	 *         currency_code: string,
	 *         value: string
	 *     }
	 * } Level 2 data array.
	 */
	private function build_level_2( ?Money $tax_total ): array {
		/**
		 * Filters the Level 2 invoice ID.
		 *
		 * @param string $invoice_id The invoice ID (default: unique cart identifier).
		 */
		$invoice_id = apply_filters(
			'woocommerce_paypal_payments_level2_invoice_id',
			'INV_' . strtoupper( uniqid() )
		);

		$level_2 = array(
			'invoice_id' => (string) substr( $invoice_id, 0, 127 ),
		);

		if ( $tax_total ) {
			$level_2['tax_total'] = array(
				'currency_code' => $tax_total->currency_code(),
				'value'         => $tax_total->value_str(),
			);
		}

		return $level_2;
	}
}
