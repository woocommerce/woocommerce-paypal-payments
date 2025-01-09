<?php
/**
 * REST endpoint to manage the payment module.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WooCommerce\PayPalCommerce\Settings\Data\PaymentSettings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class PaymentRestEndpoint extends RestEndpoint {
	/**
	 * The base path for this REST controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'payment';

	/**
	 * The settings instance.
	 *
	 * @var PaymentSettings
	 */
	protected PaymentSettings $settings;

	/**
	 * Constructor.
	 *
	 * @param PaymentSettings $settings The settings instance.
	 */
	public function __construct( PaymentSettings $settings ) {
		$this->settings = $settings;
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
					'callback'            => array( $this, 'get_payment_methods' ),
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
					'callback'            => array( $this, 'update_payment_methods' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Returns all payment methods from the DB.
	 *
	 * @return WP_REST_Response The common settings.
	 */
	public function get_payment_methods() : WP_REST_Response {
		return $this->return_success( $this->settings->to_array(), );
	}

	/**
	 * Updates common details based on the request.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response The new common settings.
	 */
	public function update_payment_methods( WP_REST_Request $request ) : WP_REST_Response {
		return $this->return_success( [] );
	}
}
