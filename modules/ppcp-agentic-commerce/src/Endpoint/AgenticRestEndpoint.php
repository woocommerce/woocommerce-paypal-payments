<?php
/**
 * Base class for all agentic commerce REST endpoints.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WC_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCartResponse;

/**
 * Base class for REST controllers in the agentic commerce module.
 */
abstract class AgenticRestEndpoint extends WC_REST_Controller {
	/**
	 * Endpoint namespace.
	 */
	protected const NAMESPACE = 'wc/v3/agentic';

	/**
	 * Verify JWT access.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool True if access is granted.
	 */
	public function check_permission( WP_REST_Request $request ): bool {
		// TODO: Implement JWT validation
		// Extract and validate PayPal JWT token from Authorization header.
		return true;
	}

	/**
	 * Returns details about a known cart that's stored in the shop's DB.
	 *
	 * @param PayPalCartResponse $cart The PayPalCart response object.
	 * @return WP_REST_Response The successful response.
	 */
	protected function return_existing_cart( PayPalCartResponse $cart ): WP_REST_Response {
		return new WP_REST_Response( $cart->to_array(), 200 );
	}

	/**
	 * Returns a new cart created during this request.
	 *
	 * @param PayPalCartResponse $cart The PayPalCart response object.
	 * @return WP_REST_Response The successful response.
	 */
	protected function return_new_cart( PayPalCartResponse $cart ): WP_REST_Response {
		return new WP_REST_Response( $cart->with_token()->to_array(), 201 );
	}

	/**
	 * Returns cart details with a payment confirmation property.
	 *
	 * @param PayPalCartResponse $cart The PayPalCart response object.
	 * @return WP_REST_Response The successful response.
	 */
	protected function return_paid_cart( PayPalCartResponse $cart ): WP_REST_Response {
		return new WP_REST_Response( $cart->with_confirmation()->to_array(), 200 );
	}
}
