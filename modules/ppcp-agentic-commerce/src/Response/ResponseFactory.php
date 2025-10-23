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
	public function new_cart( PayPalCart $cart ): NewCartResponse {
		// todo - the token represents a checkout-session, linked to the cart; via #5272-persist-cart.
		$token = wp_generate_password( 12, false );

		return new NewCartResponse( $cart, $token );
	}

	public function from_order( WC_Order $order, PayPalCart $cart ): PaidCartResponse {
		return new PaidCartResponse( $cart, $order );
	}

	public function from_cart( PayPalCart $cart ): CartResponse {
		return new CartResponse( $cart );
	}
}
