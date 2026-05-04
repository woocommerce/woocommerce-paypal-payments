<?php
/**
 * Checkout Endpoint for Agentic Commerce.
 *
 * POST /api/paypal/v1/merchant-cart/{cartId}/checkout
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Endpoint;

use WC_Order;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use Psr\Log\LoggerInterface;

use WooCommerce\PayPalCommerce\StoreSync\Errors\AgenticError;
use WooCommerce\PayPalCommerce\StoreSync\Errors\Http\InternalServerError;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\PaymentErrorContext;
use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PaymentMethod;
use WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticSessionManager;
use WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticCheckoutProcessor;
use WooCommerce\PayPalCommerce\StoreSync\Helper\PayPalOrderManager;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CartValidationProcessor;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\StoreSync\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\StoreSync\Response\ResponseFactory;

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
		AgenticSessionManager $session_manager,
		ResponseFactory $response_factory,
		CartValidationProcessor $validation_processor,
		LoggerInterface $logger,
		PayPalOrderManager $order_manager,
		AgenticCheckoutProcessor $checkout_processor
	) {

		parent::__construct( $auth_provider, $session_handler, $session_manager, $response_factory, $validation_processor, $logger, $order_manager );

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
				'callback'            => fn( $request ) => $this->with_session( fn() => $this->complete_checkout( $request ) ),
				'permission_callback' => fn( $request ) => $this->check_permission( $request ),
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
			$cart_response = $this->response_factory->from_cart( $cart, $cart_id );

			return $this->cart_details( $cart_response, 200 );
		}

		$order = $this->create_wc_order( $cart, $payment_method, $session['ec_token'] );

		if ( is_wp_error( $order ) ) {
			$issue         = ValidationIssue::create_payment_error( $order->get_error_message() )
				->add_context(
					PaymentErrorContext::create_payment_declined()
						->decline_reason( (string) $order->get_error_code() )
				);
			$cart_response = $this->response_factory->from_cart(
				$cart->with_validation_issues( $issue ),
				$cart_id
			);
			return $this->cart_details( $cart_response, 200 );
		}

		$this->flush_local_cart( $cart_id );

		$response = $this->response_factory->from_order( $order, $cart, $cart_id );

		return $this->cart_details( $response, 200 );
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
