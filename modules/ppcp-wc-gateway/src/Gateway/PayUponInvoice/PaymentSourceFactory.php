<?php

namespace WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice;

use WC_Order;

class PaymentSourceFactory {

	public function from_wc_order( WC_Order $order ) {
		$address = $order->get_address();
		$birth_date = filter_input( INPUT_POST, 'billing_birth_date', FILTER_SANITIZE_STRING );
		$phone_country_code = WC()->countries->get_country_calling_code( $address['country'] ?? '' );

		return new PaymentSource(
			$address['first_name'] ?? '',
			$address['last_name'] ?? '',
			$address['email'] ?? '',
			$birth_date ?? '',
			$address['phone'] ?? '',
			substr($phone_country_code, strlen('+')) ?? '',
			$address['address_1'] ?? '',
			$address['city'] ?? '',
			$address['postcode'] ?? '',
			$address['country'] ?? '',
			'en-DE',
			'EXAMPLE INC',
			'https://example.com/logoUrl.svg',
			array('Customer service phone is +49 6912345678.')
		);
	}
}
