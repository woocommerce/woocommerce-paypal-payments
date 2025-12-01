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

use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\NotFoundError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\AgenticError;

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
				'callback'            => array( $this, 'replace_cart' ),
				'permission_callback' => array( $this, 'check_permission' ),
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

		$new_cart = $this->parse_and_validate_cart( $request );

		if ( $new_cart instanceof AgenticError ) {
			return $this->error( $new_cart );
		}

		// Get the PayPal Order ID (ec_token).
		$paypal_order_id = $session['ec_token'];

		// Update the PayPal Order with new totals.
		try {
			$this->order_manager->update_order( $paypal_order_id, $new_cart );
		} catch ( \Exception $e ) {
			return $this->error(
				new NotFoundError(
					'Failed to update PayPal Order: ' . $e->getMessage(),
					array(
						array(
							'issue'       => 'PAYPAL_ORDER_UPDATE_FAILED',
							'description' => 'Could not synchronize cart changes with PayPal.',
						),
					)
				)
			);
		}

		// Replace the cart session (preserving ec_token).
		$update_result = $this->store_local_cart( $cart_id, $new_cart );

		if ( ! $update_result ) {
			return $this->error(
				new NotFoundError(
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

		return $this->cart_details( $response, 200 );
	}
}
