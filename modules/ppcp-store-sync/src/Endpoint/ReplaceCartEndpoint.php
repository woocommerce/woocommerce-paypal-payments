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

use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
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
	 * Per PayPal specs, the PUT endpoint may create a new PayPal order when:
	 * - The cart doesn't have an ec_token yet (POST validation failed)
	 * - The existing ec_token has expired
	 *
	 * @param WP_REST_Request $request The REST request.
	 * @return WP_REST_Response The REST response.
	 */
	public function replace_cart( WP_REST_Request $request ): WP_REST_Response {
		$cart_id = $request->get_param( 'cart_id' );

		// Verify cart exists and get current session data.
		$session = $this->get_stored_cart( $cart_id );

		if ( $session instanceof AgenticError ) {
			return $this->error( $session );
		}

		$new_cart = $this->get_cart_from_request( $request );

		if ( $new_cart instanceof AgenticError ) {
			return $this->error( $new_cart );
		}

		// Determine if we need to create a new PayPal order.
		$existing_token = $session['ec_token'] ?? '';
		$new_token      = null;

		if ( $this->needs_new_token( $existing_token, $new_cart ) ) {
			// Create new PayPal order - may return empty string if cart is invalid.
			$new_token = $this->order_manager->create_order( $new_cart ) ?: null;

			$this->logger->info(
				'[REST] PUT created new PayPal order',
				array(
					'cart_id'   => $cart_id,
					'new_token' => $new_token ?: '(none - order creation failed)',
					'reason'    => empty( $existing_token ) ? 'no_existing_token' : 'token_expired',
				)
			);
		}

		// Update the cart session, passing new token if one was created.
		$update_result = $this->store_local_cart( $cart_id, $new_cart, $new_token );

		if ( ! $update_result ) {
			return $this->error_not_found(
				'Failed to replace cart',
				array(
					'issue'       => 'CART_REPLACE_FAILED',
					'description' => 'Cart replacement operation failed.',
				)
			);
		}

		// Build response - include token only if a new one was created.
		if ( $new_token ) {
			$response = $this->response_factory->cart_with_token( $new_cart, $cart_id, $new_token );
		} else {
			$response = $this->response_factory->from_cart( $new_cart, $cart_id );
		}

		return $this->cart_details( $response, 200 );
	}

	/**
	 * Determine if a new PayPal order/token needs to be created.
	 *
	 * A new token is needed when:
	 * 1. No existing token (previous POST failed to create one)
	 * 2. Existing token has expired (future: implement expiry check)
	 *
	 * @param string     $existing_token The current ec_token from session.
	 * @param PayPalCart $cart The new cart data.
	 * @return bool True if a new token should be created.
	 */
	private function needs_new_token( string $existing_token, PayPalCart $cart ): bool {
		// Case 1: No existing token.
		if ( empty( $existing_token ) ) {
			// Only attempt to create if the cart is valid (no validation issues).
			return ! $cart->issues();
		}

		// Case 2: Token expired - could be implemented by checking PayPal order status.
		// For now, we preserve valid tokens and don't recreate them.
		// Future enhancement: call PayPal API to check if order is still valid.

		return false;
	}

	/**
	 * Create a not found error response.
	 *
	 * @param string $message Error message.
	 * @param array  $details Error details.
	 * @return WP_REST_Response The error response.
	 */
	private function error_not_found( string $message, array $details ): WP_REST_Response {
		return $this->error( new NotFoundError( $message, array( $details ) ) );
	}
}
