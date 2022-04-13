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
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			if ( WC_Subscriptions_Product::get_trial_length( $product ) > 0 ) {
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

		return $product
			&& WC_Subscriptions_Product::is_subscription( $product )
			&& WC_Subscriptions_Product::get_trial_length( $product ) > 0;
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

		return ! empty(
			array_filter(
				$subs,
				function ( WC_Subscription $sub ): bool {
					return (float) $sub->get_total_initial_payment() <= 0;
				}
			)
		);
	}
}
