<?php
/**
 * PayPal Cart Response (new cart created).
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Response
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Response;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;

class NewCartResponse extends CartResponse {

	protected string $status = 'CREATED';

	public function __construct( PayPalCart $cart, string $cart_id, string $token ) {
		parent::__construct( $cart, $cart_id );
		$this->token = $token;
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
