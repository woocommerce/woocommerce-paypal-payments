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
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\CartHelper;

class CartResponse {
	private const ALLOWED_STATUS = array(
		'CREATED',
		'INCOMPLETE',
		'READY',
		'COMPLETED',
	);

	private const ALLOWED_VALIDATION_STATUS = array(
		'VALID',
		'INVALID',
		'REQUIRES_ADDITIONAL_INFORMATION',
	);

	protected PayPalCart $cart;

	/**
	 * The cart ID used by the API to reference to an existing cart.
	 */
	private string $cart_id;

	/**
	 * Used to track cart lifecycle.
	 * Possible values: CREATED, INCOMPLETE, READY, COMPLETED
	 */
	protected string $status = 'INCOMPLETE';

	/**
	 * Used to determine the next step.
	 * Possible values: VALID, INVALID, REQUIRES_ADDITIONAL_INFORMATION
	 */
	protected string $validation_status = 'INVALID';

	/**
	 * The payment method token, used to verify checkout.
	 */
	protected string $token = '';

	public function __construct( PayPalCart $cart, string $cart_id = '' ) {
		$this->cart    = $cart;
		$this->cart_id = $cart_id;

		if ( ! $this->cart->issues() ) {
			$this->validation_status = 'VALID';
		}
	}

	/**
	 * Calculate cart totals.
	 *
	 * @return array The totals array.
	 */
	protected function calculate_totals(): array {
		$currency_code = CartHelper::currency( $this->cart );
		$item_total    = CartHelper::cart_item_total( $this->cart );

		return array(
			'item_total' => array(
				'currency_code' => $currency_code,
				'value'         => $item_total,
			),
			'shipping'   => array(
				'currency_code' => $currency_code,
				'value'         => 0.00,
			),
			'tax_total'  => array(
				'currency_code' => $currency_code,
				'value'         => 0.00,
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
		$data = array(
			'id'                => $this->cart_id,
			'status'            => $this->status(),
			'validation_status' => $this->validation_status(),
			'validation_issues' => array_map(
				static fn( ValidationIssue $issue ) => $issue->to_array(),
				$this->cart->issues()
			),
			'totals'            => $this->calculate_totals(),
		);

		return array_merge( $data, $this->cart->to_array() );
	}

	private function status(): string {
		if ( in_array( $this->status, self::ALLOWED_STATUS, true ) ) {
			return $this->status;
		}

		return 'INCOMPLETE';
	}

	private function validation_status(): string {
		if ( in_array( $this->validation_status, self::ALLOWED_VALIDATION_STATUS, true ) ) {
			return $this->validation_status;
		}

		return 'INVALID';
	}
}
