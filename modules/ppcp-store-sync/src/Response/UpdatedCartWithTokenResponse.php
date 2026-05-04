<?php
/**
 * PayPal Cart Response (cart updated with new token).
 *
 * Used when PUT /merchant-cart/{id} creates a new PayPal order because:
 * - The cart didn't have a token (POST validation failed)
 * - The existing token was expired
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Response
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Response;

use WC_Cart;

use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;

class UpdatedCartWithTokenResponse extends CartResponse {

	/**
	 * Constructor.
	 *
	 * @param PayPalCart   $cart The PayPal cart.
	 * @param string       $cart_id The cart ID.
	 * @param string       $token The EC token.
	 * @param array        $applied_coupons Applied coupons data.
	 * @param WC_Cart|null $wc_cart The WooCommerce cart.
	 */
	public function __construct(
		PayPalCart $cart,
		string $cart_id,
		string $token,
		array $applied_coupons = array(),
		?WC_Cart $wc_cart = null
	) {
		parent::__construct( $cart, $cart_id );
		$this->applied_coupons( $applied_coupons );
		$this->wc_cart( $wc_cart );
		$this->token = $token;

		// Updated carts with valid tokens are READY for checkout.
		if ( ! $this->cart->issues() && ! empty( $token ) ) {
			$this->status = 'READY';
		}
	}

	/**
	 * Convert to array for API response.
	 *
	 * Includes the payment_method.token only when a new token was created.
	 * Per PayPal spec: "The API response should only mention new token,
	 * i.e. when no token was generated, the payment_method.token property
	 * should not exist in the response."
	 *
	 * @return array The response array.
	 */
	public function to_array(): array {
		$data = parent::to_array();

		// Only include payment_method when a token exists.
		if ( ! empty( $this->token ) ) {
			$data['payment_method'] = array(
				'type'  => 'paypal', // hard-coded.
				'token' => $this->token,
			);

			// Add sandbox approval URL for testing.
			$data['_testing'] = array(
				'sandbox_approval_url' => 'https://www.sandbox.paypal.com/checkoutnow?token=' . $this->token,
				'instructions'         => 'For sandbox testing: Open this URL in browser, login as buyer, and approve payment before checkout.',
			);
		}

		return $data;
	}
}
