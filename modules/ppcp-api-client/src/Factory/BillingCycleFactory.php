<?php
/**
 * The Billing Cycle factory.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WC_Product;
use WooCommerce\PayPalCommerce\ApiClient\Entity\BillingCycle;

class BillingCycleFactory {

	public function from_wc_product(WC_Product $product): BillingCycle {
		return new BillingCycle(
			array(
				'interval_unit' => $product->get_meta('_subscription_period'),
				'interval_count' => $product->get_meta('_subscription_period_interval'),
			),
			1,
			'REGULAR',
			array(
				'fixed_price' => array(
					'value' => $product->get_meta('_subscription_price'),
					'currency_code' => 'USD',
				),
			),
			(int)$product->get_meta('_subscription_length')
		);
	}

	public function from_paypal_response(stdClass $data): BillingCycle {
		return new BillingCycle(
			array(
				'interval_unit' => $data->frequency->interval_unit,
				'interval_count' => $data->frequency->interval_count,
			),
			$data->sequence,
			$data->tenure_type,
			array(
				'fixed_price' => array(
					'value' => $data->pricing_scheme->fixed_price->value,
					'currency_code' => $data->pricing_scheme->fixed_price->currency_code,
				),
			),
			$data->total_cycles
		);
	}
}
