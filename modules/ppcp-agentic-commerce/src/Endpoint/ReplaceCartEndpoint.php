<?php
/**
 * Replace Cart Endpoint for Agentic Commerce.
 *
 * PUT /api/paypal/v1/merchant-cart/{cart_id}
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\JwtAuthService;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\AgenticError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\CartNotFoundError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;

/**
 * Replace Cart REST endpoint.
 *
 * Fully replaces an existing cart while preserving the payment token.
 */
class ReplaceCartEndpoint extends AgenticRestEndpoint {
	/**
	 * The endpoint path following PayPal specs.
	 */
	protected const PATH = 'merchant-cart/(?P<cart_id>[a-zA-Z0-9_-]+)';

	/**
	 * The expected HTTP method.
	 */
	protected const METHOD = 'PUT';

	/**
	 * The agentic session handler.
	 *
	 * @var AgenticSessionHandler
	 */
	private AgenticSessionHandler $session_handler;

	/**
	 * Constructor.
	 *
	 * @param JwtAuthService        $auth_service The JWT auth service.
	 * @param AgenticSessionHandler $session_handler The session handler.
	 * @param ResponseFactory       $response_factory The response factory.
	 */
	public function __construct(
		JwtAuthService $auth_service,
		AgenticSessionHandler $session_handler,
		ResponseFactory $response_factory
	) {
		parent::__construct( $auth_service, $response_factory );
		$this->session_handler = $session_handler;
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
				'callback'            => array( $this, 'replace_cart' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'cart_id' => array(
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && strlen( $param ) >= 10;
						},
					),
				),
			)
		);
	}

	/**
	 * Replace an existing cart with new data.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The REST response.
	 */
	public function replace_cart( WP_REST_Request $request ): WP_REST_Response {
		$cart_id = $request->get_param( 'cart_id' );

		// Verify cart exists.
		$session = $this->session_handler->load_cart_session( $cart_id );

		if ( ! $session ) {
			return $this->error(
				new CartNotFoundError(
					"Cart with ID '{$cart_id}' does not exist or has expired",
					array(
						array(
							'field'       => 'cartId',
							'issue'       => 'NOT_FOUND',
							'description' => "Cart with ID '{$cart_id}' does not exist. Verify cart ID or create a new cart.",
						),
					)
				)
			);
		}

		$data = $this->parse_json_body( $request );

		if ( $data instanceof AgenticError ) {
			return $this->error( $data );
		}

		$new_cart = PayPalCart::from_array( $data );

		// Replace the cart session (preserving ec_token).
		$update_result = $this->session_handler->update_cart_session( $cart_id, $new_cart );

		if ( ! $update_result ) {
			return $this->error(
				new CartNotFoundError(
					'Failed to replace cart',
					array(
						array(
							'issue'       => 'CART_REPLACE_FAILED',
							'description' => 'Cart replacement operation failed.',
						),
					)
				)
			);
		}

		$response = $this->response_factory->from_cart( $new_cart );

		// Return 200 OK (not 201 Created).
		return $this->cart_details( $response, 200 );
	}
}
