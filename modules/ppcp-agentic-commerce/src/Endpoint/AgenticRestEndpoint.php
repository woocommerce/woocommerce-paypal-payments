<?php
/**
 * Base class for all agentic commerce REST endpoints.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use JsonException;
use WC_REST_Controller;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\BadRequestError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\InternalServerError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\NotFoundError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidProduct;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\AgenticError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\CartResponse;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\ProductValidator;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\PayPalOrderManager;

/**
 * Base class for REST controllers in the agentic commerce module.
 */
abstract class AgenticRestEndpoint extends WC_REST_Controller {
	/**
	 * Endpoint namespace.
	 */
	protected const NAMESPACE = 'wc/v3/agentic';

	/**
	 * JWT scope(s) required for the endpoint.
	 */
	protected const REQUIRED_SCOPES = array( 'cart' );

	private AuthServiceProvider $auth_provider;

	protected AgenticSessionHandler $session_handler;

	protected ResponseFactory $response_factory;

	protected LoggerInterface $logger;

	protected ProductValidator $product_validator;

	protected PayPalOrderManager $order_manager;

	public function __construct(
		AuthServiceProvider $auth_provider,
		AgenticSessionHandler $session_handler,
		ResponseFactory $response_factory,
		LoggerInterface $logger,
		ProductValidator $product_validator,
		PayPalOrderManager $order_manager
	) {

		$this->auth_provider     = $auth_provider;
		$this->session_handler   = $session_handler;
		$this->response_factory  = $response_factory;
		$this->logger            = $logger;
		$this->product_validator = $product_validator;
		$this->order_manager     = $order_manager;
	}

	/**
	 * Verify JWT access.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error True if access is granted, otherwise a WP_Error object.
	 */
	public function check_permission( WP_REST_Request $request ) {
		$token        = $request->get_header( 'Authorization' );
		$auth_service = $this->auth_provider->auth_service();
		$context      = $auth_service->get_token( $token );

		if ( is_wp_error( $context ) ) {
			assert( $context instanceof WP_Error );

			return $context;
		}

		return $auth_service->verify_claims( $context, static::REQUIRED_SCOPES );
	}

	/**
	 * Successful API response, always returns cart details.
	 *
	 * @param CartResponse $cart        The PayPalCart response object.
	 * @param int          $status_code HTTP status code.
	 * @return WP_REST_Response The successful response.
	 */
	protected function cart_details( CartResponse $cart, int $status_code = 200 ): WP_REST_Response {
		return new WP_REST_Response( $cart->to_array(), $status_code );
	}

	/**
	 * Returns an error REST API response.
	 *
	 * @param AgenticError $error The error object.
	 * @return WP_REST_Response The error response.
	 */
	protected function error( AgenticError $error ): WP_REST_Response {
		return new WP_REST_Response( $error->to_array(), $error->get_status_code() );
	}

	/**
	 * Parses and validates JSON request body.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return array|AgenticError Parsed data or error response.
	 */
	protected function parse_json_body( WP_REST_Request $request ) {
		$body = $request->get_body();
		if ( empty( $body ) ) {
			return new InternalServerError( 'Request body is required' );
		}

		try {
			return json_decode( $body, true, 512, JSON_THROW_ON_ERROR );
		} catch ( JsonException $exception ) {
			return new InternalServerError( 'Request body contains invalid JSON. Error: ' . $exception->getMessage() );
		}
	}

	/**
	 * Parse and validate cart from request body.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return PayPalCart|AgenticError Valid cart or error.
	 */
	protected function parse_and_validate_cart( WP_REST_Request $request ) {
		$data = $this->parse_json_body( $request );

		if ( $data instanceof AgenticError ) {
			return $data;
		}

		$cart = PayPalCart::from_array( $data );

		$issues = $cart->validate();
		if ( ! empty( $issues ) ) {
			return new BadRequestError( 'Cart validation issue', $issues );
		}

		return $cart;
	}

	/**
	 * Load cart session with standardized error handling.
	 *
	 * @param string $cart_id The cart ID to load.
	 * @return array|AgenticError Cart session data or error.
	 */
	protected function load_cart_session( string $cart_id ) {
		$session = $this->session_handler->load_cart_session( $cart_id );

		if ( ! $session ) {
			return new NotFoundError(
				"Cart with ID '{$cart_id}' does not exist or has expired",
				array(
					array(
						'field'       => 'cartId',
						'issue'       => 'NOT_FOUND',
						'description' => "Cart with ID '{$cart_id}' does not exist. Verify cart ID or create a new cart.",
					),
				)
			);
		}

		return $session;
	}

	/**
	 * Standard cart ID validation callback.
	 *
	 * @param mixed $param The parameter to validate.
	 * @return bool True if valid cart ID format.
	 */
	protected function validate_cart_id( $param ): bool {
		return is_string( $param ) && strlen( $param ) >= 10;
	}

	/**
	 * Get standard cart ID argument definition for route registration.
	 *
	 * @return array Cart ID argument configuration.
	 */
	protected function get_cart_id_arg(): array {
		return array(
				'required'          => true,
				'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_cart_id' ),
		);
	}
}
