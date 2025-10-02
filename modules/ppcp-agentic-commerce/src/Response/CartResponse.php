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
	protected ?PayPalCart $cart = null;

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

	public function __construct( PayPalCart $cart ) {
		$this->cart = $cart;
		// todo - set the other props of this class, once the flow becomes more clear.
	}

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
		);

		return array_merge( $data, $this->cart->to_array() );
	}
}
