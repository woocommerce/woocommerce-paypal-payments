<?php
/**
 * Fraudnet session id.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\FraudNet;

use Exception;

/**
 * Class FraudNetSessionId.
 */
class FraudNetSessionId {

	/**
	 * Generates a session ID or use the existing one from WC session.
	 *
	 * @return array|string
	 * @throws Exception When there is a problem with the session ID.
	 */
	public function __invoke() {
		if ( WC()->session === null ) {
			return '';
		}

		if ( WC()->session->get( 'ppcp_fraudnet_session_id' ) ) {
			return WC()->session->get( 'ppcp_fraudnet_session_id' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$pui_pay_for_order_session_id = wc_clean( wp_unslash( $_POST['pui_pay_for_order_session_id'] ?? '' ) );
			if ( $pui_pay_for_order_session_id && '' !== $pui_pay_for_order_session_id ) {
				return $pui_pay_for_order_session_id;
			}
		}

		$session_id = bin2hex( random_bytes( 16 ) );
		WC()->session->set( 'ppcp_fraudnet_session_id', $session_id );

		return $session_id;
	}
}
