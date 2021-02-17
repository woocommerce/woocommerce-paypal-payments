<?php
/**
 * Helper class for the subscriptions. Contains methods to determine
 * whether the cart contains a subscription, the current product is
 * a subscription or the subscription plugin is activated in the first place.
 *
 * @package WooCommerce\PayPalCommerce\Subscription\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription\Helper;

/**
 * Class SubscriptionHelper
 */
class SubscriptionHelper {

	/**
	 * Whether the current product is a subscription.
	 *
	 * @return bool
	 */
	public function current_product_is_subscription(): bool {
		if ( ! $this->plugin_is_active() ) {
			return false;
		}
		$product = wc_get_product();
		return is_a( $product, \WC_Product::class ) && $product->is_type( 'subscription' );
	}

	/**
	 * Whether the current cart contains subscriptions.
	 *
	 * @return bool
	 */
	public function cart_contains_subscription(): bool {
		if ( ! $this->plugin_is_active() ) {
			return false;
		}
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return false;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( ! isset( $item['data'] ) || ! is_a( $item['data'], \WC_Product::class ) ) {
				continue;
			}
			if ( $item['data']->is_type( 'subscription' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether only automatic payment gateways are accepted.
	 *
	 * @return bool
	 */
	public function accept_only_automatic_payment_gateways(): bool {

		if ( ! $this->plugin_is_active() ) {
			return false;
		}
		$accept_manual_renewals = ( 'no' !== get_option(
            //phpcs:disable Inpsyde.CodeQuality.VariablesName.SnakeCaseVar
			\WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals',
            //phpcs:enable Inpsyde.CodeQuality.VariablesName.SnakeCaseVar
			'no'
		) ) ? true : false;
		return ! $accept_manual_renewals;
	}

	/**
	 * Whether the subscription plugin is active or not.
	 *
	 * @return bool
	 */
	public function plugin_is_active(): bool {

		return class_exists( \WC_Subscriptions::class );
	}
}
