<?php

namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Plan;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;

class PlanFactory
{
	/**
	 * @var BillingCycleFactory
	 */
	private $billing_cycle_factory;
	/**
	 * @var PaymentPreferencesFactory
	 */
	private $payment_preferences_factory;

	public function __construct(
		BillingCycleFactory $billing_cycle_factory,
		PaymentPreferencesFactory $payment_preferences_factory
	){
		$this->billing_cycle_factory = $billing_cycle_factory;
		$this->payment_preferences_factory = $payment_preferences_factory;
	}

	public function from_paypal_response(stdClass $data): Plan {
		if ( ! isset( $data->id ) ) {
			throw new RuntimeException(
				__( 'No id for given plan', 'woocommerce-paypal-payments' )
			);
		}
		if ( ! isset( $data->name ) ) {
			throw new RuntimeException(
				__( 'No name for plan given', 'woocommerce-paypal-payments' )
			);
		}
		if ( ! isset( $data->product_id ) ) {
			throw new RuntimeException(
				__( 'No product id for given plan', 'woocommerce-paypal-payments' )
			);
		}
		if ( ! isset( $data->billing_cycles ) ) {
			throw new RuntimeException(
				__( 'No billing cycles for given plan', 'woocommerce-paypal-payments' )
			);
		}

		$billing_cycles = array();
		foreach ($data->billing_cycles as $billing_cycle) {
			$billing_cycles[] = $this->billing_cycle_factory->from_paypal_response($billing_cycle);
		}

		$payment_preferences = $this->payment_preferences_factory->from_paypal_response($data->payment_preferences);

		return new Plan(
			$data->id,
			$data->name,
			$data->product_id,
			$billing_cycles,
			$payment_preferences,
			$data->status ?? ''
		);
	}
}
