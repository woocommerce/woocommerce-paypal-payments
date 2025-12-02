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

use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\BadRequestError;
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
		$session = $this->load_cart_session( $cart_id );

		if ( $session instanceof AgenticError ) {
			return $this->error( $session );
		}

		// Parse the update request body.
		$update_data = $this->parse_json_body( $request );

		if ( $update_data instanceof AgenticError ) {
			return $this->error( $update_data );
		}

		// Get the existing cart from session.
		$existing_cart = $session['cart'];

		// Merge update data with existing cart, preserving fields not in the update.
		// This allows partial updates (e.g., updating items without re-sending payment_method).
		$new_cart = $existing_cart->with( $update_data );

		$issues = $new_cart->validate();
		if ( ! empty( $issues ) ) {
			$issue_details = array_map(
				function ( $issue ) {
					return $issue->to_array();
				},
				$issues
			);

			return $this->error( new BadRequestError( 'Cart validation issue', $issue_details ) );
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
		$update_result = $this->session_handler->update_cart_session( $cart_id, $new_cart );

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

		$response = $this->response_factory->active_cart( $new_cart, $cart_id, $paypal_order_id );

		return $this->cart_details( $response, 200 );
	}
}
