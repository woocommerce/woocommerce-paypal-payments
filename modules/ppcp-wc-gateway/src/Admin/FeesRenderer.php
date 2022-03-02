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
		$breakdown = $wc_order->get_meta( PayPalGateway::FEES_META_KEY );
		if ( ! is_array( $breakdown ) ) {
			return '';
		}

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

		$net = $breakdown['net_amount'] ?? null;
		if ( is_array( $net ) ) {
			$html .= $this->render_money_row(
				__( 'PayPal Payout:', 'woocommerce-paypal-payments' ),
				__( 'The net total that will be credited to your PayPal account.', 'woocommerce-paypal-payments' ),
				$net['value'],
				$net['currency_code']
			);
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
	 * @return string
	 */
	private function render_money_row( string $title, string $tooltip, $value, string $currency, bool $negative = false ): string {
		/**
		 * Bad type hint in WC phpdoc.
		 *
		 * @psalm-suppress InvalidScalarArgument
		 */
		return '
			<tr>
				<td class="label">' . wc_help_tip( $tooltip ) . ' ' . esc_html( $title ) . '
				</td>
				<td width="1%"></td>
				<td class="total">
					' .
			( $negative ? ' - ' : '' ) .
			wc_price( $value, array( 'currency' => $currency ) ) . '
				</td>
			</tr>';
	}
}
