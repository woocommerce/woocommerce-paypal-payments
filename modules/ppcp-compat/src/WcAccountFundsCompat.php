<?php
/**
 * Compatibility layer for the Account Funds for WooCommerce plugin.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

/**
 * Provides Account Funds (store credit) compatibility.
 *
 * The plugin applies store credit directly via WC_Cart::set_total() and
 * WC_Order::set_total(), bypassing the standard coupon/fee getters. Unreported, the
 * credit surfaces as the gap between the WC total and the summed breakdown, which
 * AmountFactory folds into the tax line - producing a negative tax_total that PayPal
 * rejects with CANNOT_BE_NEGATIVE once Level 2 card data is sent.
 *
 * Both readers mirror the exact condition under which the plugin reduces the total,
 * so the reported discount can never exceed the reduction it accounts for.
 */
class WcAccountFundsCompat {

	/**
	 * The plugin's cart helper, holding the credit applied to the session cart.
	 */
	private const CART_CLASS = '\Kestrel\Account_Funds\Cart';

	/**
	 * Order meta carrying the credit applied to a partially covered order. Written on
	 * order creation, before payment processing, and by the plugin's pre-4.0 path too.
	 */
	private const ORDER_META = '_funds_used';

	public function register(): void {
		add_filter(
			'woocommerce_paypal_payments_cart_extra_discount',
			array( $this, 'cart_extra_discount' ),
			10,
			2
		);
		add_filter(
			'woocommerce_paypal_payments_order_extra_discount',
			array( $this, 'order_extra_discount' ),
			10,
			2
		);
		add_filter(
			'woocommerce_paypal_payments_store_api_cart_extra_discount',
			array( $this, 'store_api_cart_extra_discount' ),
			10,
			2
		);
	}

	/**
	 * Adds the store credit applied to the cart to whatever other hooks reported.
	 */
	public function cart_extra_discount( float $extra, \WC_Cart $cart ): float {
		return $extra + $this->applied_cart_credit();
	}

	/**
	 * Same for the Store API, which works in minor units on both sides of the sum.
	 */
	public function store_api_cart_extra_discount( int $extra ): int {
		$credit = $this->applied_cart_credit();

		if ( $credit <= 0.0 ) {
			return $extra;
		}

		return $extra + (int) round( $credit * 10 ** wc_get_price_decimals() );
	}

	/**
	 * Adds the store credit applied to the order to whatever other hooks reported.
	 *
	 * Read from order meta rather than the cart: the cart session is gone on the
	 * pay-for-order page, and a stored order keeps the amount it was reduced by.
	 */
	public function order_extra_discount( float $extra, \WC_Order $order ): float {
		return $extra + max( 0.0, (float) $order->get_meta( self::ORDER_META ) );
	}

	/**
	 * The credit applied to the current cart, or 0.0 when none is.
	 *
	 * Gated on partial use, which is what makes the plugin reduce the cart total.
	 * Fully covered carts are paid through the plugin's own gateway and never reach
	 * a PayPal amount, but the getter would still report a balance for them.
	 */
	private function applied_cart_credit(): float {
		$cart_class = self::CART_CLASS;

		if ( ! class_exists( $cart_class ) || ! $cart_class::is_using_account_funds_partially() ) {
			return 0.0;
		}

		return max( 0.0, (float) $cart_class::get_applied_account_funds_amount() );
	}
}
