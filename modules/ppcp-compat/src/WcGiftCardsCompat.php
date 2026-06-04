<?php
/**
 * Compatibility layer for WooCommerce Gift Cards plugin.
 *
 * @package WooCommerce\PayPalCommerce\Compat
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Compat;

use WooCommerce\PayPalCommerce\Button\Helper\Context;

/**
 * Provides WooCommerce Gift Cards plugin compatibility.
 *
 * The WC Gift Cards plugin applies its discount directly via WC_Cart::set_total()
 * and WC_Order::set_total(), bypassing the standard coupon/fee getters. This class
 * supplies the missing amounts to the PayPal order amount breakdown via the
 * extra-discount filters.
 *
 * For the cart, the discount is captured right after WC_GC sets the cart total
 * (priority 1000, after WC_GC at 999) and stored in the WC session so it is
 * available across AJAX requests.
 */
class WcGiftCardsCompat {

	private const SESSION_KEY = 'ppcp_gc_cart_discount';

	/**
	 * @var Context
	 */
	private Context $context;

	/**
	 * @param Context $context The button context helper.
	 */
	public function __construct( Context $context ) {
		$this->context = $context;
	}

	/**
	 * Registers the hooks.
	 */
	public function register(): void {
		add_action(
			'woocommerce_after_calculate_totals',
			array( $this, 'store_cart_discount' ),
			1000
		);
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
	}

	/**
	 * Runs after WC_GC (priority 999) has set the cart total. Computes the gap
	 * between the standard breakdown total and the actual cart total and stores
	 * it in the WC session so it is available in subsequent AJAX requests.
	 *
	 * @param \WC_Cart $cart The WooCommerce cart.
	 */
	public function store_cart_discount( \WC_Cart $cart ): void {
		if ( ! function_exists( 'WC_GC' ) || ! WC_GC()->cart || ! WC()->session ) {
			return;
		}

		if ( ! $this->context->is_checkout() ) {
			return;
		}

		$totals      = WC_GC()->cart->get_account_totals_breakdown();
		$gc_discount = (float) ( $totals['cart_total'] ?? 0.0 ) - (float) ( $totals['remaining_total'] ?? 0.0 );

		WC()->session->set( self::SESSION_KEY, $gc_discount ?: 0.0 );
	}

	/**
	 * Returns the total WC Gift Cards discount applied to the cart.
	 *
	 * @param float    $extra Current extra discount accumulated by other hooks.
	 * @param \WC_Cart $cart  The WooCommerce cart.
	 * @return float
	 */
	public function cart_extra_discount( float $extra, \WC_Cart $cart ): float {
		if ( ! function_exists( 'WC_GC' ) || ! WC()->session ) {
			return $extra;
		}

		$gc_discount = (float) ( WC()->session->get( self::SESSION_KEY ) ?? 0.0 );

		return $extra + ( $gc_discount ?: 0.0 );
	}

	/**
	 * Returns the total WC Gift Cards discount applied to the order.
	 *
	 * @param float     $extra Current extra discount accumulated by other hooks.
	 * @param \WC_Order $order The WooCommerce order.
	 * @return float
	 */
	public function order_extra_discount( float $extra, \WC_Order $order ): float {
		if ( ! function_exists( 'WC_GC' ) || ! WC_GC()->order ) {
			return $extra;
		}

		$gift_cards = WC_GC()->order->get_gift_cards( $order );
		$extra     += (float) ( $gift_cards['total'] ?? 0.0 );

		return $extra;
	}
}
