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

use WC_Product;
use WC_Product_Subscription_Variation;
use WC_Subscriptions;
use WC_Subscriptions_Product;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;

/**
 * Class SubscriptionHelper
 */
class SubscriptionHelper {

	/**
	 * The settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * SubscriptionHelper constructor.
	 *
	 * @param Settings $settings The settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

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
	 * Whether only automatic payment gateways are accepted.
	 *
	 * @return bool
	 */
	public function accept_only_automatic_payment_gateways(): bool {

		if ( ! $this->plugin_is_active() ) {
			return false;
		}
		$accept_manual_renewals = 'no' !== get_option(
			\WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals',
			'no'
		);
		return ! $accept_manual_renewals;
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
}
