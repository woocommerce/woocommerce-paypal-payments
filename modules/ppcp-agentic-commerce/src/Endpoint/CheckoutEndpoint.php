<?php
/**
 * Checkout Endpoint for Agentic Commerce.
 *
 * POST /api/paypal/v1/merchant-cart/{cartId}/checkout
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WP_REST_Request;
use WP_REST_Response;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\AgenticError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\InternalServerError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\NotFoundError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PaymentMethod;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InsufficientQuantity;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ItemOutOfStock;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\AgenticCheckoutProcessor;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\PayPalOrderManager;
use WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\InventoryValidator;
use WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\ProductValidator;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;

/**
 * Checkout REST endpoint.
 */
class CheckoutEndpoint extends AgenticRestEndpoint {
	/**
	 * The checkout processor service.
	 *
	 * @var AgenticCheckoutProcessor
	 */
	protected $checkout_processor;
	protected InventoryValidator $inventory_validator;

	public function __construct(
		AuthServiceProvider $auth_provider,
		AgenticSessionHandler $session_handler,
		ResponseFactory $response_factory,
		LoggerInterface $logger,
		ProductValidator $product_validator,
		PayPalOrderManager $order_manager,
		AgenticCheckoutProcessor $checkout_processor,
		InventoryValidator $inventory_validator
	) {

		parent::__construct( $auth_provider, $session_handler, $response_factory, $logger, $product_validator, $order_manager );
		$this->checkout_processor  = $checkout_processor;
		$this->inventory_validator = $inventory_validator;
	}

	/**
	 * The endpoint path following PayPal specs.
	 */
	protected const PATH = 'merchant-cart/(?P<cart_id>[a-zA-Z0-9_-]+)/checkout';

	/**
	 * The expected HTTP method.
	 */
	protected const METHOD = 'POST';

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::PATH,
			array(
				'methods'             => self::METHOD,
				'callback'            => array( $this, 'complete_checkout' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'cart_id' => $this->get_cart_id_arg(),
				),
			)
		);
	}

	/**
	 * Complete the checkout process.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The REST response.
	 */
	public function complete_checkout( WP_REST_Request $request ): WP_REST_Response {
		$cart_id = $request->get_param( 'cart_id' );
		$data    = $this->parse_json_body( $request );

		if ( $data instanceof AgenticError ) {
			return $this->error( $data );
		}

		$payment_method        = PaymentMethod::from_array( $data['payment_method'] );
		$payment_method_issues = $payment_method->validate();

		if ( ! empty( $payment_method_issues ) ) {
			return $this->error(
				new InternalServerError(
					'Payment method is required for checkout',
					$payment_method_issues
				)
			);
		}

		// Load the cart session.
		$cart_session = $this->session_handler->load_cart_session( $cart_id );
		if ( ! $cart_session ) {
			return $this->error( new NotFoundError( 'Cart not found: ' . $cart_id ) );
		}

		// Parse the incoming cart data.
		try {
			$cart = PayPalCart::from_array( $data );
		} catch ( \Exception $e ) {
			return $this->error(
				new InternalServerError( 'Invalid cart data: ' . $e->getMessage() )
			);
		}

		// Validate products exist in WooCommerce before proceeding.
		$validation_issues = $this->product_validator->validate_products_exist( $cart );
		$validation_issues = array_merge( $validation_issues, $this->inventory_validator->verify_inventory( $cart ) );

		if ( ! empty( $validation_issues ) ) {
			$cart = $cart->with_validation_issues( ...$validation_issues );
			return $this->cart_details( $this->response_factory->active_cart( $cart, $cart_id, $cart_session['ec_token'] ) );
		}

		try {
			// Create WooCommerce order.
			$order = $this->create_wc_order( $cart, $payment_method, $cart_session['ec_token'] );
			if ( is_wp_error( $order ) ) {
				return $this->error(
					InternalServerError::from_wp_error( $order )
				);
			}

			// Remove session.
			$this->session_handler->destroy_cart_session( $cart_id );

			// Build the response with payment confirmation.
			$response = $this->response_factory->from_order(
				$order,
				$cart
			);

			return $this->cart_details( $response );

		} catch ( \Exception $e ) {
			return $this->error(
				new InternalServerError(
					'A temporary system error occurred. Please try again later.'
				)
			);
		}
	}

	/**
	 * Create a WooCommerce order from the cart data.
	 *
	 * Delegates to the AgenticCheckoutProcessor service which handles:
	 * 1. Fetching the PayPal Order using the token (order ID)
	 * 2. Translating PayPalCart to WC_Cart
	 * 3. Creating WooCommerce order
	 * 4. Linking PayPal and WC orders
	 * 5. Capturing payment
	 * 6. Cleaning up temporary cart
	 *
	 * @param PayPalCart    $cart           The cart data.
	 * @param PaymentMethod $payment_method The payment method data.
	 * @param string        $paypal_order_id The PayPal Order ID (ec_token).
	 * @return \WC_Order|\WP_Error The created order or error.
	 */
	private function create_wc_order( PayPalCart $cart, PaymentMethod $payment_method, string $paypal_order_id ) {
		return $this->checkout_processor->process( $cart, $payment_method, $paypal_order_id );
	}
}
