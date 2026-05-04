<?php
/**
 * REST endpoint to handle the agentic beta banner dismiss action.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;

/**
 * Handles permanent dismissal of the agentic beta banner.
 */
class AgenticBetaBannerEndpoint extends RestEndpoint {

	public const OPTION_DISMISSED = 'ppcp_agentic_banner_dismissed';

	protected $rest_base = 'agentic-beta-banner-dismiss';

	public function register_routes(): void {
		register_rest_route(
			static::NAMESPACE,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_dismiss' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function handle_dismiss( WP_REST_Request $request ): WP_REST_Response {
		update_option( self::OPTION_DISMISSED, true );

		return $this->return_success( array( 'dismissed' => true ) );
	}
}
