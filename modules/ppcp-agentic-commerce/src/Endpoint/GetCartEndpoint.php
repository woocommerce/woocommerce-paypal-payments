<?php
/**
 * Get Cart Endpoint for Agentic Commerce.
 *
 * GET /api/paypal/v1/merchant-cart/{cart_id}
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\JwtAuthService;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\CartNotFoundError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;

/**
 * Get Cart REST endpoint.
 */
class GetCartEndpoint extends AgenticRestEndpoint {
	/**
	 * The endpoint path following PayPal specs.
	 */
	protected const PATH = 'merchant-cart/(?P<cart_id>[a-zA-Z0-9_-]+)';

	/**
	 * The expected HTTP method.
	 */
	protected const METHOD = 'GET';

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
				'callback'            => array( $this, 'get_cart' ),
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
	 * Get an existing cart.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The REST response.
	 */
	public function get_cart( WP_REST_Request $request ): WP_REST_Response {
		$cart_id = $request->get_param( 'cart_id' );

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

		$response = $this->response_factory->active_cart(
			$session['cart'],
			$cart_id,
			$session['ec_token']
		);

		return $this->cart_details( $response );
	}
}
