<?php
/**
 * Base class for all agentic commerce REST endpoints.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Errors\AgenticErrorNotFound;

/**
 * Base class for REST controllers in the agentic commerce module.
 */
class CreateCartEndpoint extends AgenticRestEndpoint {
	/**
	 * The endpoint path, defined by PayPal (don't change).
	 */
	protected const PATH = 'merchant-cart';

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			self::PATH,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_cart' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function create_cart( WP_REST_Request $request ): WP_REST_Response {
		return $this->error( new AgenticErrorNotFound( 'not implemented' ) );
	}
}
