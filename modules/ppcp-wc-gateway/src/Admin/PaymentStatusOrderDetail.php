<?php
/**
 * Renders the not captured information.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Admin
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\Admin;

use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class PaymentStatusOrderDetail
 */
class PaymentStatusOrderDetail {

	/**
	 * Renders the not captured information.
	 *
	 * @param int $wc_order_id The WooCommerce order id.
	 */
	public function render( int $wc_order_id ) {
		$wc_order = new \WC_Order( $wc_order_id );
		$intent   = $wc_order->get_meta( PayPalGateway::INTENT_META_KEY );
		$captured = $wc_order->get_meta( PayPalGateway::CAPTURED_META_KEY );

		if ( strcasecmp( $intent, 'AUTHORIZE' ) !== 0 ) {
			return;
		}

		if ( ! empty( $captured ) && wc_string_to_bool( $captured ) ) {
			return;
		}

		printf(
            // @phpcs:ignore Inpsyde.CodeQuality.LineLength.TooLong
			'<li class="wide"><p><mark class="order-status status-on-hold"><span>%1$s</span></mark></p><p>%2$s</p></li>',
			esc_html__(
				'Not captured',
				'woocommerce-paypal-payments'
			),
			esc_html__(
				'To capture the payment select capture action from the list below.',
				'woocommerce-paypal-payments'
			)
		);
	}
}
