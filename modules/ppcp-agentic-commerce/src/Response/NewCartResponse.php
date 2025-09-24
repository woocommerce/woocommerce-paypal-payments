<?php
/**
 * PayPal Cart Response (new cart created).
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Response\
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Response;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;

class NewCartResponse extends CartResponse {
	public function __construct( PayPalCart $cart, string $token ) {
		parent::__construct( $cart );
		$this->token = $token;
	}

	public function to_array(): array {
		$data = parent::to_array();

		$data['payment_method'] = array(
			'type'         => 'paypal',
			'token'        => $this->token,
			'approval_url' => 'not-implemented',
		);

		return $data;
	}
}
