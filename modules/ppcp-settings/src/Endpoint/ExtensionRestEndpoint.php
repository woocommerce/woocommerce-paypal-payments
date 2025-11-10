<?php
/**
 * Base REST endpoint for extension settings.
 *
 * Handles route registration, persistence, and standard responses.
 * Extensions only need to implement store_name() and sanitize_rest_data().
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class ExtensionRestEndpoint
 */
abstract class ExtensionRestEndpoint extends RestEndpoint {

	/**
	 * Set this to the store name (same as used in JS)
	 */
	protected $rest_base = '';

	/**
	 * Option key prefix for storing extension settings.
	 */
	private const OPTION_PREFIX = 'woocommerce-ppcp-ext-';

	/**
	 * Sanitizes and validates REST request data.
	 *
	 * Return NULL to reject the request (no changes will be saved).
	 * Return sanitized array to accept and persist the data.
	 *
	 * @param array $data Raw request data.
	 * @return array|null Sanitized data or NULL to reject.
	 */
	abstract protected function sanitize_rest_data( array $data ): ?array;

	public function register_routes(): void {
		register_rest_route(
			static::NAMESPACE,
			'/ext/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	public function get_settings(): WP_REST_Response {
		$option_key = $this->get_option_key();
		$data       = get_option( $option_key, array() );

		return $this->return_success( $data );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$raw_data       = $request->get_params();
		$sanitized_data = $this->sanitize_rest_data( $raw_data );

		// NULL means: reject the request, do not save.
		if ( null === $sanitized_data ) {
			return $this->return_error( 'Invalid data provided' );
		}

		$option_key = $this->get_option_key();
		update_option( $option_key, $sanitized_data );

		return $this->get_settings();
	}

	private function get_option_key(): string {
		return self::OPTION_PREFIX . $this->rest_base;
	}
}
