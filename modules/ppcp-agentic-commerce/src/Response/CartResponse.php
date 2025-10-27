<?php
/**
 * PayPal Cart Response.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Response
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Response;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue;

class CartResponse {
	protected PayPalCart $cart;

	/**
	 * The cart ID used by the API to reference to an existing cart.
	 *
	 * @var string The cart ID is usually part of the REST endpoint path.
	 */
	protected string $cart_id = '';

	/**
	 * Used to track cart lifecycle.
	 * Possible values: CREATED, INCOMPLETE, READY, COMPLETED
	 *
	 * @var string Business workflow state.
	 */
	protected string $status = 'INCOMPLETE';

	/**
	 * Used to determine the next step.
	 * Possible values: VALID, INVALID, REQUIRES_ADDITIONAL_INFORMATION
	 *
	 * @var string Data validation state.
	 */
	protected string $validation_status = 'INVALID';

	/**
	 * The payment method token, used to verify checkout.
	 */
	protected string $token = '';

	/**
	 * Constructor.
	 *
	 * @param PayPalCart $cart The PayPal cart.
	 */
	public function __construct( PayPalCart $cart ) {
		$this->cart = $cart;

		$validation_issues = $this->cart->validate();
		if ( empty( $validation_issues ) ) {
			$this->validation_status = 'VALID';
		}
	}

	/**
	 * Calculate cart totals.
	 *
	 * @return array The totals array.
	 */
	protected function calculate_totals(): array {
		$cart_array = $this->cart->to_array();

		$currency_code = $cart_array['items'][0]['price']['currency_code'] ?? 'USD';

		$item_total = array_reduce(
			$cart_array['items'] ?? array(),
			function ( float $sum, $item ): float {
				return $sum + ( (float) $item['price']['value'] * $item['quantity'] );
			},
			0.0
		);

		return array(
			'item_total' => array(
				'currency_code' => $currency_code,
				'value'         => $item_total,
			),
			'shipping'   => array(
				'currency_code' => $currency_code,
				'value'         => '0.00',
			),
			'tax_total'  => array(
				'currency_code' => $currency_code,
				'value'         => '0.00',
			),
			'amount'     => array(
				'currency_code' => $currency_code,
				'value'         => $item_total,
			),
		);
	}

	/**
	 * Convert to array for API response.
	 *
	 * @return array The response array.
	 */
	public function to_array(): array {
		// Always set via the constructor, but the IDE does not believe it.
		assert( $this->cart instanceof PayPalCart );

		$data = array(
			'id'                => $this->cart_id,
			'status'            => $this->status,
			'validation_status' => $this->validation_status,
			'validation_issues' => array_map(
				static fn( ValidationIssue $issue ) => $issue->to_array(),
				$this->cart->validate()
			),
			'totals'            => $this->calculate_totals(),
		);

		return array_merge( $data, $this->cart->to_array() );
	}
}
