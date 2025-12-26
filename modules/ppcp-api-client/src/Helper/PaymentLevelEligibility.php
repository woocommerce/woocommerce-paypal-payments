<?php
/**
 * Checks eligibility for Level 2/3 card processing.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Helper;

use WC_Order;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;

class PaymentLevelEligibility {

	protected SettingsProvider $settings;
	protected string $country;

	public function __construct( SettingsProvider $settings, string $country ) {
		$this->settings = $settings;
		$this->country  = $country;
	}

	/**
	 * Checks if order is eligible for Level 2/3 processing.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return bool True if eligible.
	 */
	public function is_eligible( WC_Order $order ): bool {
		if ( ! $this->settings->payment_level_processing() ) {
			return false;
		}

		if ( ! $this->is_valid_country() ) {
			return false;
		}

		if ( ! $this->is_valid_currency( $order ) ) {
			return false;
		}

		if ( ! $this->is_valid_payment_method( $order ) ) {
			return false;
		}

		/**
		 * Filters whether an order is eligible for Level 2/3 processing.
		 *
		 * @param bool     $is_eligible Whether the order is eligible.
		 * @param WC_Order $order       The WooCommerce order.
		 */
		return apply_filters(
			'woocommerce_paypal_payments_level_processing_eligible',
			true,
			$order
		);
	}

	private function is_valid_country(): bool {
		/**
		 * Filters the allowed countries for Level 2/3 processing.
		 *
		 * @param array $countries Array of allowed country codes.
		 */
		$allowed_countries = apply_filters(
			'woocommerce_paypal_payments_level_processing_countries',
			array( 'US' )
		);

		return in_array( $this->country, $allowed_countries, true );
	}

	private function is_valid_currency( WC_Order $order ): bool {
		/**
		 * Filters the allowed currencies for Level 2/3 processing.
		 *
		 * @param array $currencies Array of allowed currency codes.
		 */
		$allowed_currencies = apply_filters(
			'woocommerce_paypal_payments_level_processing_currencies',
			array( 'USD' )
		);

		return in_array( $order->get_currency(), $allowed_currencies, true );
	}

	private function is_valid_payment_method( WC_Order $order ): bool {
		/**
		 * Filters the allowed payment methods for Level 2/3 processing.
		 *
		 * @param array $methods Array of allowed payment method IDs.
		 */
		$allowed_methods = apply_filters(
			'woocommerce_paypal_payments_level_processing_payment_methods',
			array( 'ppcp-credit-card-gateway' )
		);

		return in_array( $order->get_payment_method(), $allowed_methods, true );
	}
}
