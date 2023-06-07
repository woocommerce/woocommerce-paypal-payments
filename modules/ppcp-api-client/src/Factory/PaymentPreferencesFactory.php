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

/**
 * Class PaymentPreferencesFactory
 */
class PaymentPreferencesFactory {

	/**
	 * The currency.
	 *
	 * @var string
	 */
	private $currency;

	/**
	 * PaymentPreferencesFactory constructor.
	 *
	 * @param string $currency The currency.
	 */
	public function __construct( string $currency ) {
		$this->currency = $currency;
	}

	/**
	 * Returns a PaymentPreferences object from the given WC product.
	 *
	 * @param WC_Product $product WC product.
	 * @return PaymentPreferences
	 */
	public function from_wc_product( WC_Product $product ):PaymentPreferences {
		return new PaymentPreferences(
			array(
				'value'         => $product->get_meta( '_subscription_sign_up_fee' ) ?: '0',
				'currency_code' => $this->currency,
			)
		);
	}

	/**
	 * Returns a PaymentPreferences object based off a PayPal response.
	 *
	 * @param stdClass $data The data.
	 * @return PaymentPreferences
	 */
	public function from_paypal_response( stdClass $data ) {
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
