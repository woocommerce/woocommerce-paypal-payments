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
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Errors\AgenticError;
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
	 * Successful API response, always returns cart details.
	 *
	 * @param PayPalCartResponse $cart The PayPalCart response object.
	 * @return WP_REST_Response The successful response.
	 */
	protected function cart_details( PayPalCartResponse $cart, int $status_code = 200 ): WP_REST_Response {
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
}
