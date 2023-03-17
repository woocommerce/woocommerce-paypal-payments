<?php
/**
 * The Payment Preferences factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WC_Product;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentPreferences;

class PaymentPreferencesFactory {

	public function from_wc_product(WC_Product $product):PaymentPreferences {
		return new PaymentPreferences(
			array(
				'value'         => $product->get_meta( '_subscription_sign_up_fee' ) ?: '0',
				'currency_code' => 'USD',
			)
		);
	}

	public function from_paypal_response(stdClass $data) {
		return new PaymentPreferences(
			array(
				'value'         => $data->setup_fee->value,
				'currency_code' => $data->setup_fee->currency_code,
			),
			$data->auto_bill_outstanding,
			$data->setup_fee_failure_action,
			$data->payment_failure_threshold
		);
	}
}
