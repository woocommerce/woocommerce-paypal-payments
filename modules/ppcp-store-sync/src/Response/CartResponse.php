<?php
/**
 * PayPal Cart Response.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Response
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Response;

use WC_Order;

use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;

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

	private StorePayPalCart $store_cart;

	private array $applied_coupons = array();

	private array $shipping_options = array();

	/**
	 * The cart ID used by the API to reference to an existing cart.
	 */
	private string $cart_id;

	/**
	 * Used to track cart lifecycle.
	 * Possible values: CREATED, INCOMPLETE, READY, COMPLETED
	 */
	private string $status = 'INCOMPLETE';

	/**
	 * Used to determine the next step.
	 * Possible values: VALID, INVALID, REQUIRES_ADDITIONAL_INFORMATION
	 */
	private string $validation_status = 'INVALID';

	/**
	 * The WooCommerce order created during checkout, only set for completed carts.
	 */
	private ?WC_Order $wc_order = null;

	/**
	 * @param StorePayPalCart $store_cart The enriched cart.
	 * @param string          $cart_id    The cart ID.
	 */
	private function __construct( StorePayPalCart $store_cart, string $cart_id = '' ) {
		$this->store_cart = $store_cart;
		$this->cart_id    = $cart_id;

		if ( $store_cart->validation()->is_empty() ) {
			$this->validation_status = 'VALID';
		}
	}

	/**
	 * Create a base cart response (status: INCOMPLETE).
	 */
	public static function create( StorePayPalCart $store_cart, string $cart_id ): self {
		return new self( $store_cart, $cart_id );
	}

	/**
	 * Create a new cart response (status: CREATED).
	 */
	public static function create_new( StorePayPalCart $store_cart, string $cart_id ): self {
		$instance = new self( $store_cart, $cart_id );

		$instance->status = 'CREATED';

		return $instance;
	}

	/**
	 * Create a completed cart response (status: COMPLETED).
	 */
	public static function create_completed( StorePayPalCart $store_cart, string $cart_id, WC_Order $wc_order ): self {
		$instance = new self( $store_cart, $cart_id );

		$instance->status   = 'COMPLETED';
		$instance->wc_order = $wc_order;

		return $instance;
	}

	/**
	 * Configures the CartResponse instance - only used by the ResponseFactory.
	 *
	 * @param array $coupons Applied coupons data.
	 * @return $this
	 */
	public function applied_coupons( array $coupons ): self {
		$this->applied_coupons = $coupons;

		return $this;
	}

	/**
	 * Configures the CartResponse instance - only used by the ResponseFactory.
	 *
	 * @param array $options Available shipping options.
	 * @return $this
	 */
	public function shipping_options( array $options ): self {
		$this->shipping_options = $options;

		return $this;
	}

	/**
	 * Convert to array for API response.
	 *
	 * @return array The response array.
	 */
	public function to_array(): array {
		$raw = $this->store_cart->to_array();

		$payment_method = array( 'type' => 'paypal' );
		$paypal_order   = $this->store_cart->paypal_order();
		if ( $paypal_order ) {
			$payment_method['token'] = $paypal_order;
		}

		$data = array(
			'id'                         => $this->cart_id,
			'status'                     => $this->status(),
			'validation_status'          => $this->validation_status(),
			'validation_issues'          => array_map(
				static fn( ValidationIssue $issue ) => $issue->to_array(),
				$this->store_cart->validation()->all()
			),
			'items'                      => $raw['items'] ?? null,
			'customer'                   => $raw['customer'] ?? null,
			'shipping_address'           => $raw['shipping_address'] ?? null,
			'billing_address'            => $raw['billing_address'] ?? null,
			'available_shipping_options' => ! empty( $this->shipping_options ) ? $this->shipping_options : null,
			'totals'                     => $raw['totals'] ?? null,
			'payment_method'             => $payment_method,
			'applied_coupons'            => ! empty( $this->applied_coupons ) ? $this->applied_coupons : null,
			'payment_confirmation'       => $this->wc_order ? array(
				'merchant_order_number' => $this->wc_order->get_id(),
				'order_review_page'     => $this->wc_order->get_checkout_order_received_url(),
			) : null,
		);

		return array_filter( $data, static fn( $v ) => $v !== null );
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
