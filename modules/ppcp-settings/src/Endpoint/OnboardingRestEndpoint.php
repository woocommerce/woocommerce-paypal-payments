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
use WP_REST_Request;
use WooCommerce\PayPalCommerce\Settings\Data\OnboardingProfile;

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
	 * The settings instance.
	 *
	 * @var OnboardingProfile
	 */
	protected $profile;

	/**
	 * Constructor.
	 *
	 * @param OnboardingProfile $profile The settings instance.
	 */
	public function __construct( OnboardingProfile $profile ) {
		$this->profile = $profile;
	}

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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_details' ),
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
		$details = $this->profile->get_data();

		return rest_ensure_response( $details );
	}


	/**
	 * Receives an object with onboarding details and persists it in the DB.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response The current state of the onboarding wizard.
	 */
	public function update_details( WP_REST_Request $request ) : WP_REST_Response {
		$details = $this->profile->get_data();

		$get_param = fn( $key ) => wc_clean( wp_unslash( $request->get_param( $key ) ) );

		$raw_step      = $get_param( 'step' );
		$raw_completed = $get_param( 'completed' );

		if ( is_numeric( $raw_step ) ) {
			$details['step'] = intval( $raw_step );
		}
		if ( null !== $raw_completed ) {
			$details['completed'] = (bool) $raw_completed;
		}

		$this->profile->save_data( $details );

		return rest_ensure_response( $details );
	}
}
