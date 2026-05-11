<?php
/**
 * PayPal Cart Response.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Response
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Response;

use WC_Cart;
use WC_Order;

use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\StoreSync\Helper\CartHelper;
use WooCommerce\PayPalCommerce\StoreSync\Config\StoreCurrencyValue;

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

	private PayPalCart $cart;

	private StoreValidation $validation;

	private string $default_currency = '';

	private ?WC_Cart $wc_cart = null;

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
	 * The payment method token, only set for newly created carts.
	 */
	private string $token = '';

	/**
	 * The WooCommerce order created during checkout, only set for completed carts.
	 */
	private ?WC_Order $wc_order = null;

	/**
	 * @param PayPalCart      $cart       The PayPal cart.
	 * @param StoreValidation $validation The validation state for this request.
	 * @param string          $cart_id    The cart ID.
	 */
	private function __construct( PayPalCart $cart, StoreValidation $validation, string $cart_id = '' ) {
		$this->cart       = $cart;
		$this->validation = $validation;
		$this->cart_id    = $cart_id;

		if ( $validation->is_empty() ) {
			$this->validation_status = 'VALID';
		}
	}

	/**
	 * Create a base cart response (status: INCOMPLETE).
	 *
	 * @param PayPalCart      $cart       The PayPal cart.
	 * @param string          $cart_id    The cart ID.
	 * @param StoreValidation $validation The validation state for this request.
	 * @return self
	 */
	public static function create( PayPalCart $cart, string $cart_id, StoreValidation $validation ): self {
		return new self( $cart, $validation, $cart_id );
	}

	/**
	 * Create a new cart response (status: CREATED).
	 *
	 * @param PayPalCart      $cart       The PayPal cart.
	 * @param string          $cart_id    The cart ID.
	 * @param string          $token      The EC token.
	 * @param StoreValidation $validation The validation state for this request.
	 * @return self
	 */
	public static function create_new( PayPalCart $cart, string $cart_id, string $token, StoreValidation $validation ): self {
		$instance = new self( $cart, $validation, $cart_id );

		$instance->status = 'CREATED';
		$instance->token  = $token;

		return $instance;
	}

	/**
	 * Create a completed cart response (status: COMPLETED).
	 *
	 * @param PayPalCart      $cart       The PayPal cart.
	 * @param string          $cart_id    The cart ID.
	 * @param WC_Order        $wc_order   The WooCommerce order.
	 * @param StoreValidation $validation The validation state for this request.
	 * @return self
	 */
	public static function create_completed( PayPalCart $cart, string $cart_id, WC_Order $wc_order, StoreValidation $validation ): self {
		$instance = new self( $cart, $validation, $cart_id );

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
	 * Configures the CartResponse instance - only used by the ResponseFactory.
	 *
	 * @param WC_Cart|null $wc_cart The WooCommerce cart, used to calculate totals.
	 * @return $this
	 */
	public function wc_cart( ?WC_Cart $wc_cart ): self {
		$this->wc_cart = $wc_cart;

		return $this;
	}

	/**
	 * Configures the CartResponse instance - only used by the ResponseFactory.
	 *
	 * @param StoreCurrencyValue $store_currency Resolves the WooCommerce currency code.
	 * @return $this
	 */
	public function store_currency( StoreCurrencyValue $store_currency ): self {
		$this->default_currency = $store_currency->value();

		return $this;
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
				$this->validation->all()
			),
			'payment_method'    => array( 'type' => 'paypal' ),
		);

		if ( ! empty( $this->applied_coupons ) ) {
			$data['applied_coupons'] = $this->applied_coupons;
		}

		$totals = $this->calculate_totals();

		if ( $totals ) {
			$data['totals'] = $totals;
		}

		if ( ! empty( $this->shipping_options ) ) {
			$data['available_shipping_options'] = $this->shipping_options;
		}

		if ( $this->token ) {
			$data['payment_method']['token'] = $this->token;
		}

		if ( $this->wc_order ) {
			$data['payment_confirmation'] = array(
				'merchant_order_number' => $this->wc_order->get_id(),
				'order_review_page'     => $this->wc_order->get_checkout_order_received_url(),
			);
		}

		return $data;
	}

	/**
	 * Calculate cart totals.
	 *
	 * @return array|null The cart-totals array, or null if not calculable.
	 */
	private function calculate_totals(): ?array {
		if ( ! $this->wc_cart || $this->validation->has_pricing_issue() ) {
			return null;
		}

		return CartHelper::calculate_totals( $this->wc_cart, $this->currency_code() );
	}

	private function currency_code(): string {
		return CartHelper::currency( $this->cart, $this->default_currency );
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
