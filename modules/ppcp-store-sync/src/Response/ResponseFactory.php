<?php
/**
 * Factory service for the REST response objects.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Response
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Response;

use WC_Cart;
use WC_Order;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CouponValidator\AppliedCouponsBuilder;
use WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCartBuilder;
use WooCommerce\PayPalCommerce\StoreSync\Helper\ShippingOptionsBuilder;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;

class ResponseFactory {

	private AgenticCartBuilder $cart_builder;
	private AppliedCouponsBuilder $applied_coupons_builder;
	private ShippingOptionsBuilder $shipping_options_builder;
	private StoreCurrencyValue $store_currency;

	public function __construct(
		AgenticCartBuilder $cart_builder,
		AppliedCouponsBuilder $applied_coupons_builder,
		ShippingOptionsBuilder $shipping_options_builder,
		StoreCurrencyValue $store_currency
	) {
		$this->cart_builder             = $cart_builder;
		$this->applied_coupons_builder  = $applied_coupons_builder;
		$this->shipping_options_builder = $shipping_options_builder;
		$this->store_currency           = $store_currency;
	}

	/**
	 * Create a new cart response (status: CREATED).
	 *
	 * @param PayPalCart $cart    The cart object.
	 * @param string     $cart_id The cart ID.
	 * @param string     $token   The payment token.
	 * @return CartResponse The response object.
	 */
	public function new_cart( PayPalCart $cart, string $cart_id, string $token ): CartResponse {
		$wc_cart = $this->build_wc_cart_or_null( $cart );

		return CartResponse::create_new( $cart, $cart_id, $token )
			->wc_cart( $wc_cart )
			->store_currency( $this->store_currency )
			->applied_coupons( $this->build_applied_coupons( $cart ) )
			->shipping_options( $this->shipping_options_builder->build( $wc_cart ) );
	}

	/**
	 * Create a cart response with token (for PUT when a new token was created).
	 *
	 * Used for PUT /merchant-cart/{id} when the endpoint creates a new PayPal order
	 * because the cart didn't have a token or the existing token was expired.
	 *
	 * @param PayPalCart $cart The cart object.
	 * @param string     $cart_id The cart ID.
	 * @param string     $token The payment token.
	 * @return UpdatedCartWithTokenResponse The response object.
	 */
	public function cart_with_token( PayPalCart $cart, string $cart_id, string $token ): UpdatedCartWithTokenResponse {
		$wc_cart         = $this->build_wc_cart_or_null( $cart );
		$applied_coupons = $this->build_applied_coupons( $cart );
		return new UpdatedCartWithTokenResponse( $cart, $cart_id, $token, $applied_coupons, $wc_cart );
	}

	/**
	 * Create a paid cart response.
	 *
	 * @param WC_Order   $order   The WooCommerce order.
	 * @param PayPalCart $cart    The cart object.
	 * @param string     $cart_id The cart ID.
	 * @return CartResponse The response object.
	 */
	public function from_order( WC_Order $order, PayPalCart $cart, string $cart_id ): CartResponse {
		$wc_cart = $this->build_wc_cart_or_null( $cart );

		return CartResponse::create_completed( $cart, $cart_id, $order )
			->wc_cart( $wc_cart )
			->store_currency( $this->store_currency )
			->applied_coupons( $this->build_applied_coupons( $cart ) )
			->shipping_options( $this->shipping_options_builder->build( $wc_cart ) );
	}

	/**
	 * Create a basic cart response.
	 *
	 * @param PayPalCart $cart    The cart object.
	 * @param string     $cart_id The cart ID.
	 * @return CartResponse The response object.
	 */
	public function from_cart( PayPalCart $cart, string $cart_id ): CartResponse {
		$wc_cart = $this->build_wc_cart_or_null( $cart );

		return CartResponse::create( $cart, $cart_id )
			->wc_cart( $wc_cart )
			->store_currency( $this->store_currency )
			->applied_coupons( $this->build_applied_coupons( $cart ) )
			->shipping_options( $this->shipping_options_builder->build( $wc_cart ) );
	}

	/**
	 * Build WC_Cart from PayPalCart.
	 *
	 * @param PayPalCart $cart The PayPal cart.
	 * @return WC_Cart|null The WooCommerce cart or null.
	 */
	private function build_wc_cart_or_null( PayPalCart $cart ): ?WC_Cart {
		$wc_cart = $this->cart_builder->paypal_cart_to_wc_cart( $cart );

		if ( $wc_cart instanceof WC_Cart ) {
			return $wc_cart;
		}

		return null;
	}

	/**
	 * Build applied coupons data for a cart.
	 *
	 * @param PayPalCart $cart The cart object.
	 * @return array Applied coupons data.
	 */
	private function build_applied_coupons( PayPalCart $cart ): array {
		$validation_status = $cart->issues() ? 'INVALID' : 'VALID';

		return $this->applied_coupons_builder->build_applied_coupons_array( $cart, $validation_status );
	}
}
