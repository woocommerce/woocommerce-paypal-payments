<?php
/**
 * PayPal Cart Response.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Response
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Response;

class PayPalCartResponse {
	/**
	 * The full cart details
	 * todo implement this!
	 */
	protected array $data = array();

	/**
	 * The payment method token, used to verify checkout.
	 */
	protected string $token = '';

	public function to_array(): array {
		return $this->data;
	}
}
