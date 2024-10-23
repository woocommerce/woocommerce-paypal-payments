<?php
/**
 * Helper class for the subscriptions. Contains methods to determine
 * whether the cart contains a subscription, the current product is
 * a subscription or the subscription plugin is activated in the first place.
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcSubscriptions\Helper;

use WC_Order;
use WC_Product;
use WC_Product_Subscription_Variation;
use WC_Subscription;
use WC_Subscriptions;
use WC_Subscriptions_Product;
use WCS_Manual_Renewal_Manager;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;

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
		return $product && WC_Subscriptions_Product::is_subscription( $product );
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
			if ( ! isset( $item['data'] ) || ! is_a( $item['data'], WC_Product::class ) ) {
				continue;
			}
			if ( WC_Subscriptions_Product::is_subscription( $item['data'] ) ) {
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

		return $this->has_subscription( $order_id );
	}

	/**
	 * Whether manual renewals are accepted.
	 *
	 * @return bool
	 */
	public function accept_manual_renewals(): bool {
		if ( ! class_exists( WCS_Manual_Renewal_Manager::class ) ) {
			return false;
		}
		return WCS_Manual_Renewal_Manager::is_manual_renewal_enabled();
	}

	/**
	 * Whether the subscription plugin is active or not.
	 *
	 * @return bool
	 */
	public function plugin_is_active(): bool {

		return class_exists( WC_Subscriptions::class ) && class_exists( WC_Subscriptions_Product::class );
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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['pay_for_order'] ) || ! isset( $_GET['change_payment_method'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks whether subscription needs subscription intent.
	 *
	 * @param string $subscription_mode The subscription mode.
	 * @return bool
	 */
	public function need_subscription_intent( string $subscription_mode ): bool {
		if ( $subscription_mode === 'subscriptions_api' ) {
			if (
				$this->current_product_is_subscription()
				|| ( ( is_cart() || is_checkout() ) && $this->cart_contains_subscription() )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if subscription product is allowed.
	 *
	 * @return bool
	 * @throws NotFoundException If setting is not found.
	 */
	public function checkout_subscription_product_allowed(): bool {
		if (
			! $this->paypal_subscription_id()
			|| ! $this->cart_contains_only_one_item()
		) {
			return false;
		}

		return true;
	}

	/**
	 * Returns PayPal subscription plan id from WC subscription product.
	 *
	 * @return string
	 */
	public function paypal_subscription_id(): string {
		if ( $this->current_product_is_subscription() ) {
			$product = wc_get_product();
			assert( $product instanceof WC_Product );

			if ( $product->get_type() === 'subscription' && $product->meta_exists( 'ppcp_subscription_plan' ) ) {
				return $product->get_meta( 'ppcp_subscription_plan' )['id'];
			}
		}

		$cart = WC()->cart ?? null;
		if ( ! $cart || $cart->is_empty() ) {
			return '';
		}
		$items = $cart->get_cart_contents();
		foreach ( $items as $item ) {
			$product = wc_get_product( $item['product_id'] );
			assert( $product instanceof WC_Product );

			if ( $product->get_type() === 'subscription' && $product->meta_exists( 'ppcp_subscription_plan' ) ) {
				return $product->get_meta( 'ppcp_subscription_plan' )['id'];
			}

			if ( $product->get_type() === 'variable-subscription' ) {
				/**
				 * The method is defined in WC_Product_Variable class.
				 *
				 * @psalm-suppress UndefinedMethod
				 */
				$product_variations = $product->get_available_variations();
				foreach ( $product_variations as $variation ) {
					$variation_product = wc_get_product( $variation['variation_id'] ) ?? '';
					if ( $variation_product && $variation_product->meta_exists( 'ppcp_subscription_plan' ) ) {
						return $variation_product->get_meta( 'ppcp_subscription_plan' )['id'];
					}
				}
			}
		}

		return '';
	}

	/**
	 * Returns variations for variable PayPal subscription product.
	 *
	 * @return array
	 */
	public function variable_paypal_subscription_variations(): array {
		$variations = array();
		if ( ! $this->current_product_is_subscription() ) {
			return $variations;
		}

		$product = wc_get_product();
		assert( $product instanceof WC_Product );
		if ( $product->get_type() !== 'variable-subscription' ) {
			return $variations;
		}

		$variation_ids = $product->get_children();
		foreach ( $variation_ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! is_a( $product, WC_Product_Subscription_Variation::class ) ) {
				continue;
			}

			$subscription_plan = $product->get_meta( 'ppcp_subscription_plan' ) ?? array();
			$variations[]      = array(
				'id'                => $product->get_id(),
				'attributes'        => $product->get_attributes(),
				'subscription_plan' => $subscription_plan['id'] ?? '',
			);
		}

		return $variations;
	}

	/**
	 * Checks if cart contains only one item.
	 *
	 * @return bool
	 */
	public function cart_contains_only_one_item(): bool {
		if ( ! $this->plugin_is_active() ) {
			return false;
		}
		$cart = WC()->cart;
		if ( ! $cart || $cart->is_empty() ) {
			return false;
		}

		if ( count( $cart->get_cart() ) > 1 ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the locations on the page which have subscription products.
	 *
	 * @return array
	 */
	public function locations_with_subscription_product(): array {
		return array(
			'product'  => is_product() && $this->current_product_is_subscription(),
			'payorder' => is_wc_endpoint_url( 'order-pay' ) && $this->order_pay_contains_subscription(),
			'cart'     => $this->cart_contains_subscription(),
		);
	}

	/**
	 * Returns previous order transaction from the given subscription.
	 *
	 * @param WC_Subscription $subscription WooCommerce Subscription.
	 * @return string
	 */
	public function previous_transaction( WC_Subscription $subscription ): string {
		$orders = $subscription->get_related_orders( 'ids', array( 'parent', 'renewal' ) );
		if ( ! $orders ) {
			return '';
		}

		// Sort orders by key descending.
		krsort( $orders );

		// Removes first order (the current processing order).
		unset( $orders[ array_key_first( $orders ) ] );

		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( is_a( $order, WC_Order::class ) && in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
				$transaction_id = $order->get_transaction_id();
				if ( $transaction_id ) {
					return $transaction_id;
				}
			}
		}

		return '';
	}

	/**
	 * Returns the variation subscription plan id from the cart.
	 *
	 * @return string
	 */
	public function paypal_subscription_variation_from_cart(): string {
		$cart = WC()->cart ?? null;
		if ( ! $cart || $cart->is_empty() ) {
			return '';
		}

		$items = $cart->get_cart_contents();
		foreach ( $items as $item ) {
			$variation_id = $item['variation_id'] ?? 0;
			if ( $variation_id ) {
				$variation_product = wc_get_product( $variation_id ) ?? '';
				if ( $variation_product && $variation_product->meta_exists( 'ppcp_subscription_plan' ) ) {
					return $variation_product->get_meta( 'ppcp_subscription_plan' )['id'];
				}
			}
		}

		return '';
	}
}
