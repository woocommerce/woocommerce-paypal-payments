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

use WC_Order;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use Psr\Log\LoggerInterface;

use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\AgenticError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\InternalServerError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PaymentMethod;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\AgenticCheckoutProcessor;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\PayPalOrderManager;
use WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CartValidationProcessor;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;

/**
 * Checkout REST endpoint.
 */
class CheckoutEndpoint extends AgenticRestEndpoint {

	/**
	 * The endpoint path following PayPal specs.
	 */
	protected const PATH = 'merchant-cart/(?P<cart_id>[a-zA-Z0-9_-]+)/checkout';

	/**
	 * The expected HTTP method.
	 */
	protected const METHOD = 'POST';

	protected AgenticCheckoutProcessor $checkout_processor;

	public function __construct(
		AuthServiceProvider $auth_provider,
		AgenticSessionHandler $session_handler,
		ResponseFactory $response_factory,
		CartValidationProcessor $validation_processor,
		LoggerInterface $logger,
		PayPalOrderManager $order_manager,
		AgenticCheckoutProcessor $checkout_processor
	) {

		parent::__construct( $auth_provider, $session_handler, $response_factory, $validation_processor, $logger, $order_manager );

		$this->checkout_processor = $checkout_processor;
	}

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

		// TODO: Move this into a validator to add a PAYMENT_ERROR, which we can check here.
		$payment_method        = PaymentMethod::from_array( $data['payment_method'] );
		$payment_method_issues = $payment_method->issues();

		if ( ! empty( $payment_method_issues ) ) {
			return $this->error(
				new InternalServerError(
					'Payment method is required for checkout',
					$payment_method_issues
				)
			);
		}

		$session = $this->get_stored_cart( $cart_id );
		if ( $session instanceof AgenticError ) {
			return $this->error( $session );
		}

		// Parse the incoming cart data.
		$cart = $this->get_cart_from_request( $request );
		if ( $cart instanceof AgenticError ) {
			return $this->error( $cart );
		}

		// If the cart has _any_ validation issue, stop here.
		if ( $cart->issues() ) {
			$cart_response = $this->response_factory->from_cart( $cart );

			return $this->cart_details( $cart_response, 200 );
		}

		$order = $this->create_wc_order( $cart, $payment_method, $session['ec_token'] );

		if ( is_wp_error( $order ) ) {
			return $this->error( InternalServerError::from_wp_error( $order ) );
		}

		$this->flush_local_cart( $cart_id );

		$response = $this->response_factory->from_order( $order, $cart );

		return $this->cart_details( $response );
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
	 * @param PayPalCart    $cart            The cart data.
	 * @param PaymentMethod $payment_method  The payment method data.
	 * @param string        $paypal_order_id The PayPal Order ID (ec_token).
	 * @return WC_Order|WP_Error The created order or error.
	 */
	private function create_wc_order( PayPalCart $cart, PaymentMethod $payment_method, string $paypal_order_id ) {
		return $this->checkout_processor->process( $cart, $payment_method, $paypal_order_id );
	}
}
