<?php

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

if (!class_exists('AbstractPaymentMethodType')) {
	/**
	 * Stub for WooCommerce Blocks AbstractPaymentMethodType
	 */
	abstract class AbstractPaymentMethodType {
		/**
		 * Payment method name/id/slug.
		 *
		 * @var string
		 */
		protected $name = '';

		/**
		 * Get payment method name.
		 *
		 * @return string
		 */
		public function get_name() {
			return $this->name;
		}

		/**
		 * Returns an array of scripts/handles to be registered for this payment method.
		 *
		 * @return array
		 */
		abstract public function get_payment_method_script_handles();

		/**
		 * Returns an array of key=>value pairs of data made available to the payment methods script.
		 *
		 * @return array
		 */
		public function get_payment_method_data() {
			return [];
		}

		/**
		 * An array of supported features for this payment method.
		 *
		 * @return string[]
		 */
		public function get_supported_features() {
			return [];
		}

		/**
		 * Initialize the payment method.
		 */
		public function initialize() {
			// Stub method
		}

		/**
		 * Returns true if the payment method is active.
		 *
		 * @return boolean
		 */
		public function is_active() {
			return true;
		}
	}
}
