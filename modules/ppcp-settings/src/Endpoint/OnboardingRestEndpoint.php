<?php
/**
 * REST endpoint to manage the onboarding module.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Server;
use WP_REST_Response;

/**
 * REST controller for the onboarding module.
 *
 * Responsible for persisting and loading the state of the onboarding wizard.
 */
class OnboardingRestEndpoint extends RestEndpoint {
	/**
	 * The base path for this REST controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'onboarding';

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_details' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Returns an object with all details of the current onboarding wizard
	 * progress.
	 *
	 * @return WP_REST_Response The current state of the onboarding wizard.
	 */
	public function get_details() : WP_REST_Response {
		// TODO: Retrieve the onboarding details from the database.
		$details = array(
			'step'      => 1,
			'completed' => false,
		);

		return rest_ensure_response( $details );
	}
}
