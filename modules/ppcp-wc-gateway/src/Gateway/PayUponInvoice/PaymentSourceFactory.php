<?php

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

use WC_Order;

class PaymentSourceFactory {

	public function from_wc_order( WC_Order $order ) {

		return new PaymentSource();
	}
}
