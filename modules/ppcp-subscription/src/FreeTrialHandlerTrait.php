<?php
/**
 * Helper trait for the subscriptions handling.
 *
 * @package WooCommerce\PayPalCommerce\Subscription
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription;

use WC_Order;
use WC_Product;
use WC_Subscription;
use WC_Subscriptions_Product;
use WC_Subscriptions_Synchroniser;

/**
 * Class FreeTrialHandlerTrait
 */
trait FreeTrialHandlerTrait {
	use SubscriptionsHandlerTrait;

	/**
	 * Checks if the cart contains only free trial.
	 *
	 * @return bool
	 */
	protected function is_free_trial_cart(): bool {
		if ( ! $this->is_wcs_plugin_active() ) {
			return false;
		}

		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() || (float) $cart->get_total( 'numeric' ) > 0 ) {
			return false;
		}

		foreach ( $cart->get_cart() as $item ) {
			$product = $item['data'] ?? null;
			if ( $product && WC_Subscriptions_Product::is_subscription( $product ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the current product contains free trial.
	 *
	 * @return bool
	 */
	protected function is_free_trial_product(): bool {
		if ( ! $this->is_wcs_plugin_active() ) {
			return false;
		}

		$product = wc_get_product();

		if ( ! $product || ! WC_Subscriptions_Product::is_subscription( $product ) ) {
			return false;
		}

		if ( WC_Subscriptions_Product::get_trial_length( $product ) > 0 ) {
			return true;
		}

		if ( WC_Subscriptions_Synchroniser::is_product_synced( $product ) && ! WC_Subscriptions_Synchroniser::is_payment_upfront( $product ) ) {
			$date = WC_Subscriptions_Synchroniser::calculate_first_payment_date( $product, 'timestamp' );
			if ( ! WC_Subscriptions_Synchroniser::is_today( $date ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the given order contains only free trial.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @return bool
	 */
	protected function is_free_trial_order( WC_Order $wc_order ): bool {
		if ( ! $this->is_wcs_plugin_active() ) {
			return false;
		}

		if ( (float) $wc_order->get_total( 'numeric' ) > 0 ) {
			return false;
		}

		$subs = wcs_get_subscriptions_for_order( $wc_order );

		return ! empty( $subs );
	}
}
