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
	public function __construct( PayPalCart $cart, string $token ) {
		parent::__construct( $cart );
		$this->token = $token;

		$this->status = 'CREATED';

		// todo - the cart_id is generated when we persist the new cart; via #5272-persist-cart.
		$this->cart_id = wp_generate_password( 12, false );
	}

	public function to_array(): array {
		$data = parent::to_array();

		$method = PaymentMethod::from_array(
			array(
				'type'  => 'paypal', // hard-coded.
				'token' => $this->token,
			)
		);

		$data['payment_method'] = $method->to_array();

		return $data;
	}
}
