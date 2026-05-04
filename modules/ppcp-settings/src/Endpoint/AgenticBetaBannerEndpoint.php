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
 * Handles banner interactions for the agentic beta program:
 * permanent dismissal and survey application.
 */
class AgenticBetaBannerEndpoint extends RestEndpoint {

	public const OPTION_DISMISSED = 'ppcp_agentic_banner_dismissed';
	public const OPTION_STATUS    = 'ppcp_agentic_beta_status';
	public const STATUS_PENDING   = 'pending';

	protected $rest_base = 'agentic-beta-banner';

	public function register_routes(): void {
		register_rest_route(
			static::NAMESPACE,
			'/' . $this->rest_base . '/dismiss',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_dismiss' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			static::NAMESPACE,
			'/' . $this->rest_base . '/apply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_apply' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	public function handle_dismiss( WP_REST_Request $request ): WP_REST_Response {
		update_option( self::OPTION_DISMISSED, true );

		return $this->return_success( array( 'dismissed' => true ) );
	}

	public function handle_apply( WP_REST_Request $request ): WP_REST_Response {
		update_option( self::OPTION_STATUS, self::STATUS_PENDING );

		return $this->return_success( array( 'status' => self::STATUS_PENDING ) );
	}
}
