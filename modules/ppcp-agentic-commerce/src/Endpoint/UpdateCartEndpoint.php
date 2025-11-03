<?php
/**
 * Update Cart Endpoint for Agentic Commerce.
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
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\UpdateFailedError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;

/**
 * Update Cart REST endpoint.
 */
class UpdateCartEndpoint extends AgenticRestEndpoint {
	/**
	 * The endpoint path following PayPal specs.
	 */
	protected const PATH = 'merchant-cart/(?P<cart_id>[a-zA-Z0-9_-]+)';

	/**
	 * The expected HTTP method.
	 */
	protected const METHOD = 'PUT';

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
				'callback'            => array( $this, 'update_cart' ),
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
	 * Update an existing cart (partial update).
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The REST response.
	 */
	public function update_cart( WP_REST_Request $request ): WP_REST_Response {
		$cart_id = $request->get_param( 'cart_id' );

		// Load existing cart first.
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

		// Merge new data with existing cart data (partial update).
		$existing_cart_array = $session['cart']->to_array();
		$merged_data         = array_merge( $existing_cart_array, $data );

		$updated_cart = PayPalCart::from_array( $merged_data );

		$update_result = $this->session_handler->update_cart_session( $cart_id, $updated_cart );

		if ( ! $update_result ) {
			return $this->error(
				new UpdateFailedError(
					'Failed to update cart',
					array(
						array(
							'issue'       => 'CART_UPDATE_FAILED',
							'description' => 'Cart update operation failed.',
						),
					)
				)
			);
		}

		// Reload the updated session.
		$session = $this->session_handler->load_cart_session( $cart_id );

		if ( ! $session ) {
			return $this->error(
				new UpdateFailedError(
					'Failed to verify cart update',
					array(
						array(
							'issue'       => 'CART_UPDATE_VERIFICATION_FAILED',
							'description' => 'Cart was updated but could not be verified.',
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
