<?php
/**
 * Factory service for the REST response objects.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Response
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Response;

use WC_Order;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;

class ResponseFactory {
	/**
	 * Create a new cart response (status: CREATED).
	 *
	 * @param PayPalCart $cart The cart object.
	 * @param string     $cart_id The cart ID.
	 * @param string     $token The payment token.
	 * @return NewCartResponse The response object.
	 */
	public function new_cart( PayPalCart $cart, string $cart_id, string $token ): NewCartResponse {
		return new NewCartResponse( $cart, $cart_id, $token, 'CREATED' );
	}

	/**
	 * Create an active cart response (status: ACTIVE).
	 *
	 * @param PayPalCart $cart The cart object.
	 * @param string     $cart_id The cart ID.
	 * @param string     $token The payment token.
	 * @return NewCartResponse The response object.
	 */
	public function active_cart( PayPalCart $cart, string $cart_id, string $token ): NewCartResponse {
		return new NewCartResponse( $cart, $cart_id, $token, 'ACTIVE' );
	}

	/**
	 * Create a paid cart response.
	 *
	 * @param WC_Order   $order The WooCommerce order.
	 * @param PayPalCart $cart The cart object.
	 * @return PaidCartResponse The response object.
	 */
	public function from_order( WC_Order $order, PayPalCart $cart ): PaidCartResponse {
		return new PaidCartResponse( $cart, $order );
	}

	/**
	 * Create a basic cart response.
	 *
	 * @param PayPalCart $cart The cart object.
	 * @return CartResponse The response object.
	 */
	public function from_cart( PayPalCart $cart ): CartResponse {
		return new CartResponse( $cart );
	}
}
