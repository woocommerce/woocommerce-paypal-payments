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
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Errors\AgenticErrorInvalidRequest;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Errors\AgenticError;

/**
 * Base class for REST controllers in the agentic commerce module.
 */
class CreateCartEndpoint extends AgenticRestEndpoint {
	/**
	 * The endpoint path, defined by PayPal (don't change).
	 */
	protected const PATH = 'merchant-cart';

	/**
	 * The expected HTTP method; must match the PayPal docs.
	 */
	protected const METHOD = 'POST';

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

	public function create_cart( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->parse_json_body( $request );

		if ( $data instanceof AgenticError ) {
			return $this->error( $data );
		}

		return $this->error( new AgenticErrorInvalidRequest( 'Not Implemented' ) );
	}
}
