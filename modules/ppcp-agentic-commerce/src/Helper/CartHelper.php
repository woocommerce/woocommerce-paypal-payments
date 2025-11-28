<?php
/**
 * Cart Helper for Agentic Commerce.
 *
 * Provides convenience methods for accessing and calculating cart data.
 * Schema classes remain pure data structures; all convenience logic goes here.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Helper;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\CartItem;

class CartHelper {
	/**
	 * Derives currency from the first cart item's price.
	 */
	public static function currency( PayPalCart $cart, string $default = '' ): string {
		$items = $cart->items();

		if ( empty( $items ) ) {
			return $default;
		}

		$first_item = $items[0];
		$price      = $first_item->price();

		if ( ! $price ) {
			return $default;
		}

		return $price->currency_code() ?? $default;
	}

	/**
	 * Sums (price * quantity) for each item. Items without price are treated as 0.0.
	 */
	public static function cart_item_total( PayPalCart $cart ): float {
		return array_reduce(
			$cart->items(),
			static function ( float $cart_total, CartItem $item ): float {
				$price = $item->price();
				if ( ! $price || ! $price->value() ) {
					return $cart_total;
				}

				return $cart_total + ( $price->value() * (float) $item->quantity() );
			},
			0.0
		);
	}
}
