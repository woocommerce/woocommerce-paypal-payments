<?php
/**
 * Replace Cart Endpoint for Agentic Commerce.
 *
 * PUT /api/paypal/v1/merchant-cart/{cart_id}
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\StoreSync\Endpoint;

use WP_REST_Request;
use WP_REST_Response;

use WooCommerce\PayPalCommerce\StoreSync\Errors\Http\NotFoundError;
use WooCommerce\PayPalCommerce\StoreSync\Errors\AgenticError;

/**
 * Replace Cart REST endpoint.
 *
 * Fully replaces an existing cart while preserving the payment token.
 */
class ReplaceCartEndpoint extends AgenticRestEndpoint {
	/**
	 * The endpoint path following PayPal specs.
	 */
	private const PATH = 'merchant-cart/(?P<cart_id>[a-zA-Z0-9_-]+)';

	/**
	 * The expected HTTP method.
	 */
	private const METHOD = 'PUT';

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
				'callback'            => fn( $request ) => $this->with_session( fn() => $this->replace_cart( $request ) ),
				'permission_callback' => fn( $request ) => $this->check_permission( $request ),
				'args'                => array(
					'cart_id' => $this->get_cart_id_arg(),
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
		$session = $this->get_stored_cart( $cart_id );

		if ( $session instanceof AgenticError ) {
			return $this->error( $session );
		}

		$new_cart = $this->get_cart_from_request( $request );

		if ( $new_cart instanceof AgenticError ) {
			return $this->error( $new_cart );
		}

		// Replace the cart session (preserving ec_token).
		$update_result = $this->store_local_cart( $cart_id, $new_cart );

		if ( ! $update_result ) {
			return $this->error_not_found(
				'Failed to replace cart',
				array(
					'issue'       => 'CART_REPLACE_FAILED',
					'description' => 'Cart replacement operation failed.',
				)
			);
		}

		$response = $this->response_factory->from_cart( $new_cart, $cart_id, $this->validation );

		return $this->cart_details( $response, 200 );
	}

	private function error_not_found( string $message, array $details ): WP_REST_Response {
		return $this->error( new NotFoundError( $message, array( $details ) ) );
	}
}
