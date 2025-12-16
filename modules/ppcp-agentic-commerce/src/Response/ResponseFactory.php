<?php
/**
 * Factory service for the REST response objects.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Response
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Response;

use WC_Cart;
use WC_Order;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\AgenticCartBuilder;

class ResponseFactory {
	private AgenticCartBuilder $cart_builder;

	public function __construct( AgenticCartBuilder $cart_builder ) {
		$this->cart_builder = $cart_builder;
	}

	public function new_cart( PayPalCart $cart, string $cart_id, string $token ): NewCartResponse {
		$wc_cart = $this->build_wc_cart_or_null( $cart );

		// The only response that includes the token!
		return new NewCartResponse( $cart, $cart_id, $wc_cart, $token );
	}

	public function from_order( WC_Order $order, PayPalCart $cart, string $cart_id ): PaidCartResponse {
		$wc_cart = $this->build_wc_cart_or_null( $cart );

		return new PaidCartResponse( $cart, $cart_id, $wc_cart, $order );
	}

	public function from_cart( PayPalCart $cart, string $cart_id ): CartResponse {
		$wc_cart = $this->build_wc_cart_or_null( $cart );

		return new CartResponse( $cart, $cart_id, $wc_cart );
	}

	private function build_wc_cart_or_null( PayPalCart $cart ): ?WC_Cart {
		$wc_cart = $this->cart_builder->paypal_cart_to_wc_cart( $cart );

		if ( $wc_cart instanceof WC_Cart ) {
			return $wc_cart;
		}

		return null;
	}
}
