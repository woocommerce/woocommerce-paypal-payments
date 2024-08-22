<?php
/**
 * Renders the PayPal fees in the order details.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Admin
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Admin;

use WC_Order;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class FeesRenderer
 */
class FeesRenderer {
	/**
	 * Renders the PayPal fees in the order details.
	 *
	 * @param WC_Order $wc_order The order for which to render the fees.
	 *
	 * @return string
	 */
	public function render( WC_Order $wc_order ) : string {
		$breakdown        = $wc_order->get_meta( PayPalGateway::FEES_META_KEY );
		$refund_breakdown = $wc_order->get_meta( PayPalGateway::REFUND_FEES_META_KEY ) ?: array();

		if ( ! is_array( $breakdown ) ) {
			return '';
		}

		$refund_fee      = $refund_breakdown['paypal_fee'] ?? array();
		$refund_amount   = $refund_breakdown['net_amount'] ?? array();
		$refund_total    = ( $refund_fee['value'] ?? 0 ) + ( $refund_amount['value'] ?? 0 );
		$refund_currency = ( ( $refund_amount['currency_code'] ?? '' ) === ( $refund_fee['currency_code'] ?? '' ) ) ? ( $refund_amount['currency_code'] ?? '' ) : '';

		$html = '';

		$fee = $breakdown['paypal_fee'] ?? null;
		if ( is_array( $fee ) ) {
			$html .= $this->render_money_row(
				__( 'PayPal Fee:', 'woocommerce-paypal-payments' ),
				__( 'The fee PayPal collects for the transaction.', 'woocommerce-paypal-payments' ),
				$fee['value'],
				$fee['currency_code'],
				true
			);
		}

		if ( $refund_fee ) {
			$html .= $this->render_money_row(
				__( 'PayPal Refund Fee:', 'woocommerce-paypal-payments' ),
				__( 'The fee PayPal collects for the refund transactions.', 'woocommerce-paypal-payments' ),
				$refund_fee['value'],
				$refund_fee['currency_code'],
				true,
				'refunded-total'
			);
		}

		if ( $refund_amount ) {
			$html .= $this->render_money_row(
				__( 'PayPal Refunded:', 'woocommerce-paypal-payments' ),
				__( 'The net amount that was refunded.', 'woocommerce-paypal-payments' ),
				$refund_amount['value'],
				$refund_amount['currency_code'],
				true,
				'refunded-total'
			);
		}

		$net = $breakdown['net_amount'] ?? null;
		if ( is_array( $net ) ) {
			$html .= $this->render_money_row(
				__( 'PayPal Payout:', 'woocommerce-paypal-payments' ),
				__( 'The net total that will be credited to your PayPal account.', 'woocommerce-paypal-payments' ),
				$net['value'],
				$net['currency_code']
			);

			if ( ( $refund_total > 0.0 && $refund_currency === $net['currency_code'] ) ) {
				$html .= $this->render_money_row(
					__( 'PayPal Net Total:', 'woocommerce-paypal-payments' ),
					__( 'The net total that will be credited to your PayPal account minus the refunds.', 'woocommerce-paypal-payments' ),
					$net['value'] - $refund_total,
					$net['currency_code']
				);
			}
		}

		return $html;
	}

	/**
	 * Renders a row in the order price breakdown table.
	 *
	 * @param string       $title The row title.
	 * @param string       $tooltip The title tooltip.
	 * @param string|float $value The money value.
	 * @param string       $currency The currency code.
	 * @param bool         $negative Whether to add the minus sign.
	 * @param string       $html_class Html class to add to the elements.
	 * @return string
	 */
	private function render_money_row( string $title, string $tooltip, $value, string $currency, bool $negative = false, string $html_class = '' ): string {
		/**
		 * Bad type hint in WC phpdoc.
		 *
		 * @psalm-suppress InvalidScalarArgument
		 */
		return '
			<tr>
				<td class="' . trim( 'label ' . $html_class ) . '">' . wc_help_tip( $tooltip ) . ' ' . esc_html( $title ) . '
				</td>
				<td width="1%"></td>
				<td class="' . trim( 'total ' . $html_class ) . '">
					' .
			( $negative ? ' - ' : '' ) .
			wc_price( $value, array( 'currency' => $currency ) ) . '
				</td>
			</tr>';
	}
}
