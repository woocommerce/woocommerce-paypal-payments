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
			if ( $item['data']->is_type( 'subscription' ) || $item['data']->is_type( 'subscription_variation' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether pay for order contains subscriptions.
	 *
	 * @return bool
	 */
	public function order_pay_contains_subscription(): bool {
		if ( ! $this->plugin_is_active() || ! is_wc_endpoint_url( 'order-pay' ) ) {
			return false;
		}

		global $wp;
		$order_id = (int) $wp->query_vars['order-pay'];
		if ( 0 === $order_id ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( is_a( $order, \WC_Order::class ) ) {
			foreach ( $order->get_items() as $item ) {
				if ( is_a( $item, \WC_Order_Item_Product::class ) ) {
					$product = wc_get_product( $item->get_product_id() );
					/**
					 * Class already exist in subscriptions plugin.
					 *
					 * @psalm-suppress UndefinedClass
					 */
					if ( is_a( $product, \WC_Product_Subscription::class ) ) {
						return true;
					}
				}
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

	/**
	 * Checks if order contains subscription.
	 *
	 * @param  int $order_id The order Id.
	 * @return boolean Whether order is a subscription or not.
	 */
	public function has_subscription( $order_id ): bool {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * Checks if page is pay for order and change subscription payment page.
	 *
	 * @return bool Whether page is change subscription or not.
	 */
	public function is_subscription_change_payment(): bool {
		$pay_for_order         = filter_input( INPUT_GET, 'pay_for_order', FILTER_SANITIZE_STRING );
		$change_payment_method = filter_input( INPUT_GET, 'change_payment_method', FILTER_SANITIZE_STRING );
		return ( isset( $pay_for_order ) && isset( $change_payment_method ) );
	}
}
