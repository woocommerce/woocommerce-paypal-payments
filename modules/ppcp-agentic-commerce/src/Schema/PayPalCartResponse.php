<?php
/**
 * PayPal Cart Response.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

class PayPalCartResponse {
	/**
	 * Whether to include the payment token; only on initial cart creation.
	 */
	private bool $include_token = false;

	/**
	 * Whether to include order confirmation details, after successful checkout.
	 */
	private bool $include_confirmation = false;

	public function with_token(): PayPalCartResponse {
		$clone                       = clone $this;
		$clone->include_token        = true;
		$clone->include_confirmation = false;

		return $clone;
	}

	public function with_confirmation(): PayPalCartResponse {
		$clone                       = clone $this;
		$clone->include_confirmation = true;
		$clone->include_token        = false;

		return $clone;
	}

	public function to_array(): array {
		$data = array( /* ... standard cart properties ... */ );

		if ( $this->include_token ) {
			$data['payment_method'] = array(
				'type'         => 'paypal',
				'token'        => 'not-implemented',
				'approval_url' => 'not-implemented',
			);
		}

		if ( $this->include_confirmation ) {
			$data['payment_confirmation'] = array(
				'merchant_order_number' => 'not-implemented',
				'order_review_page'     => 'not-implemented',
			);
		}

		return $data;
	}
}
