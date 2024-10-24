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
 * This API acts as the intermediary between the "external world" and our
 * internal data model.
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
	protected OnboardingProfile $profile;

	/**
	 * Field mapping for request to profile transformation.
	 *
	 * @var array
	 */
	private array $field_map = array(
		'step'                  => array(
			'js_name'  => 'step',
			'sanitize' => 'to_number',
		),
		'use_sandbox'           => array(
			'js_name'  => 'useSandbox',
			'sanitize' => 'to_boolean',
		),
		'use_manual_connection' => array(
			'js_name'  => 'useManualConnection',
			'sanitize' => 'to_boolean',
		),
		'client_id'             => array(
			'js_name'  => 'clientId',
			'sanitize' => 'sanitize_text_field',
		),
		'client_secret'         => array(
			'js_name'  => 'clientSecret',
			'sanitize' => 'sanitize_text_field',
		),
	);

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
	 * Returns all details of the current onboarding wizard progress.
	 *
	 * @return WP_REST_Response The current state of the onboarding wizard.
	 */
	public function get_details() : WP_REST_Response {
		$js_data = $this->sanitize_for_javascript(
			$this->profile->to_array(),
			$this->field_map
		);

		return rest_ensure_response( $js_data );
	}

	/**
	 * Updates onboarding details based on the request.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response The updated state of the onboarding wizard.
	 */
	public function update_details( WP_REST_Request $request ) : WP_REST_Response {
		$wp_data = $this->sanitize_for_wordpress(
			$request->get_params(),
			$this->field_map
		);

		$this->profile->from_array( $wp_data );
		$this->profile->save();

		return $this->get_details();
	}
}
