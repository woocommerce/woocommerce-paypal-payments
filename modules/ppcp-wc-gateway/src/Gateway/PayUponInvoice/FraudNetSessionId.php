<?php

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

class FraudNetSessionId {

	public function __invoke() {
		if ( WC()->session->get( 'ppcp_fraudnet_session_id' ) ) {
			return WC()->session->get( 'ppcp_fraudnet_session_id' );
		}

		$session_id = bin2hex( random_bytes( 16 ) );
		WC()->session->set( 'ppcp_fraudnet_session_id', $session_id );

		return $session_id;
	}
}
