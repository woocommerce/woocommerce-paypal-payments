<?php
/**
 * PayPal Cart Response (new cart created).
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

class PayPalNewCartResponse extends PayPalCartResponse {
	public function __construct() {
		$this->create_new_token();
	}

	private function create_new_token(): void {
		$this->token = 'not-implemented';
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
