<?php
/**
 * PayPal Cart Response (new cart created).
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Response
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Response;

use WC_Cart;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;

class NewCartResponse extends CartResponse {

	/**
	 * Constructor.
	 *
	 * @param PayPalCart   $cart The PayPal cart.
	 * @param string       $cart_id The cart ID.
	 * @param string       $token The EC token.
	 * @param string       $status The cart status (CREATED or ACTIVE).
	 * @param array        $applied_coupons Applied coupons data.
	 * @param WC_Cart|null $wc_cart The WooCommerce cart.
	 */
	public function __construct(
		PayPalCart $cart,
		string $cart_id,
		string $token,
		string $status = 'CREATED',
		array $applied_coupons = array(),
		?WC_Cart $wc_cart = null
	) {
		parent::__construct( $cart, $applied_coupons, $cart_id, $wc_cart );
		$this->token  = $token;
		$this->status = $status;
	}

	public function to_array(): array {
		$data = parent::to_array();

		// For security reasons, the token is only included in the "New Cart" response.
		$data['payment_method'] = array(
			'type'  => 'paypal', // hard-coded.
			'token' => $this->token,
		);

		// Add sandbox approval URL for testing.
		// In production, PayPal Commerce Platform handles approval automatically.
		$data['_testing'] = array(
			'sandbox_approval_url' => 'https://www.sandbox.paypal.com/checkoutnow?token=' . $this->token,
			'instructions'         => 'For sandbox testing: Open this URL in browser, login as buyer, and approve payment before checkout.',
		);

		return $data;
	}
}
