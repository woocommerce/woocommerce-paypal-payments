<?php
/**
 * Create Cart Endpoint for Agentic Commerce.
 *
 * POST /api/paypal/v1/merchant-cart
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\JwtAuthService;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\AgenticError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;

/**
 * Create Cart REST endpoint.
 */
class CreateCartEndpoint extends AgenticRestEndpoint {
	/**
	 * The endpoint path following PayPal specs.
	 */
	protected const PATH = 'merchant-cart';

	/**
	 * The expected HTTP method.
	 */
	protected const METHOD = 'POST';

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
				'callback'            => array( $this, 'create_cart' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Create a new cart.
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The REST response.
	 */
	public function create_cart( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->parse_json_body( $request );

		if ( $data instanceof AgenticError ) {
			return $this->error( $data );
		}

		$cart = PayPalCart::from_array( $data );

		// TODO (#5272): Generate EC token via PayPal Orders API.
		$ec_token = wp_generate_password( 12, false );

		$cart_id = $this->session_handler->create_cart_session( $cart, $ec_token );

		$response = $this->response_factory->new_cart( $cart, $cart_id, $ec_token );

		return $this->cart_details( $response, 201 );
	}
}
