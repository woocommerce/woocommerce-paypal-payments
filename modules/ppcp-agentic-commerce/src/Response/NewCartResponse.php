<?php
/**
 * PayPal Cart Response (new cart created).
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Response\
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Response;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PaymentMethod;

class NewCartResponse extends CartResponse {

	/**
	 * Constructor.
	 *
	 * @param PayPalCart $cart The PayPal cart.
	 * @param string     $cart_id The cart ID.
	 * @param string     $token The EC token.
	 * @param string     $status The cart status (CREATED or ACTIVE).
	 */
	public function __construct(
		PayPalCart $cart,
		string $cart_id,
		string $token,
		string $status = 'CREATED'
	) {
		parent::__construct( $cart );
		$this->cart_id = $cart_id;
		$this->token   = $token;
		$this->status  = $status;
	}

	/**
	 * Convert to array for API response.
	 *
	 * @return array The response array.
	 */
	public function to_array(): array {
		$data = parent::to_array();

		$method = PaymentMethod::from_array(
			array(
				'type'  => 'paypal', // hard-coded.
				'token' => $this->token,
			)
		);

		$data['payment_method'] = $method->to_array();

		// Add sandbox approval URL for testing.
		// In production, PayPal Commerce Platform handles approval automatically.
		$data['_testing'] = array(
			'sandbox_approval_url' => 'https://www.sandbox.paypal.com/checkoutnow?token=' . $this->token,
			'instructions'         => 'For sandbox testing: Open this URL in browser, login as buyer, and approve payment before checkout.',
		);

		return $data;
	}
}
