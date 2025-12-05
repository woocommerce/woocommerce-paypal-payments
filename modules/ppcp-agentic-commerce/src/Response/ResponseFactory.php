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
	public function new_cart( PayPalCart $cart, string $cart_id, string $token ): NewCartResponse {
		// The only response that includes the token!
		return new NewCartResponse( $cart, $cart_id, $token );
	}

	public function from_order( WC_Order $order, PayPalCart $cart ): PaidCartResponse {
		return new PaidCartResponse( $cart, $order );
	}

	public function from_cart( PayPalCart $cart ): CartResponse {
		return new CartResponse( $cart );
	}
}
