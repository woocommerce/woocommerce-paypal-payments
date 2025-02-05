<?php
/**
 * REST endpoint to reset dismissed things to do items.
 *
 * Provides endpoint for resetting all dismissed todos
 * via WP REST API route.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;

/**
 * Class ResetDismissedTodosEndpoint
 *
 * Handles REST API endpoint for resetting dismissed things to do items.
 */
class ResetDismissedTodosEndpoint extends RestEndpoint {
	/**
	 * The base path for this REST controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'reset-dismissed-todos';

	/**
	 * Registers the REST API route for resetting todos.
	 */
	public function register_routes(): void {
		/**
		 * POST wc/v3/wc_paypal/reset-dismissed-todos
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'reset_dismissed_todos' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Resets all dismissed todos.
	 *
	 * @param WP_REST_Request $request The request instance.
	 * @return WP_REST_Response The response containing reset status.
	 */
	public function reset_dismissed_todos( WP_REST_Request $request ): WP_REST_Response {
		$settings = get_option( 'ppcp-settings', array() );

		$settings['dismissedTodos'] = array();

		// Clear the completedOnClickTodos for testing purposes.
		// $settings['completedOnClickTodos'] = array();.

		$update_result = update_option( 'ppcp-settings', $settings );

		if ( ! $update_result ) {
			return $this->return_error( __( 'Failed to reset dismissed todos.', 'woocommerce-paypal-payments' ) );
		}

		return $this->return_success(
			array(
				'message' => __( 'Dismissed todos reset successfully.', 'woocommerce-paypal-payments' ),
			)
		);
	}
}
