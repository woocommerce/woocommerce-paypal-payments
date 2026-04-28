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
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCartBuilder;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;

class ResponseFactory {

	private AgenticCartBuilder $cart_builder;
	private AppliedCouponsBuilder $applied_coupons_builder;
	private StoreCurrencyValue $store_currency;

	/**
	 * Constructor.
	 *
	 * @param AgenticCartBuilder    $cart_builder            Cart builder service.
	 * @param AppliedCouponsBuilder $applied_coupons_builder Applied coupons builder service.
	 * @param StoreCurrencyValue    $store_currency          Woo's currency code.
	 */
	public function __construct(
		AgenticCartBuilder $cart_builder,
		AppliedCouponsBuilder $applied_coupons_builder,
		StoreCurrencyValue $store_currency
	) {
		$this->cart_builder            = $cart_builder;
		$this->applied_coupons_builder = $applied_coupons_builder;
		$this->store_currency          = $store_currency;
	}

	/**
	 * Create a new cart response (status: CREATED).
	 *
	 * @param PayPalCart $cart    The cart object.
	 * @param string     $cart_id The cart ID.
	 * @param string     $token   The payment token.
	 * @return NewCartResponse The response object.
	 */
	public function new_cart( PayPalCart $cart, string $cart_id, string $token ): NewCartResponse {
		$wc_cart         = $this->build_wc_cart_or_null( $cart );
		$applied_coupons = $this->build_applied_coupons( $cart );

		return new NewCartResponse( $cart, $cart_id, $token, $applied_coupons, $wc_cart );
	}

	/**
	 * Create a paid cart response.
	 *
	 * @param WC_Order   $order   The WooCommerce order.
	 * @param PayPalCart $cart    The cart object.
	 * @param string     $cart_id The cart ID.
	 * @return PaidCartResponse The response object.
	 */
	public function from_order( WC_Order $order, PayPalCart $cart, string $cart_id ): PaidCartResponse {
		$wc_cart         = $this->build_wc_cart_or_null( $cart );
		$applied_coupons = $this->build_applied_coupons( $cart );

		return new PaidCartResponse( $cart, $cart_id, $order, $applied_coupons, $wc_cart );
	}

	/**
	 * Create a basic cart response.
	 *
	 * @param PayPalCart $cart    The cart object.
	 * @param string     $cart_id The cart ID.
	 * @return CartResponse The response object.
	 */
	public function from_cart( PayPalCart $cart, string $cart_id ): CartResponse {
		$wc_cart         = $this->build_wc_cart_or_null( $cart );
		$applied_coupons = $this->build_applied_coupons( $cart );

		return new CartResponse( $cart, $applied_coupons, $cart_id, $wc_cart );
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
