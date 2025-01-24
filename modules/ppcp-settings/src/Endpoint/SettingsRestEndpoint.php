<?php
/**
 * REST endpoint to manage PayPal Commerce Settings Tab.
 *
 * Provides endpoints for retrieving and updating PayPal settings (Settings Tab)
 * via WP REST API routes.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WooCommerce\PayPalCommerce\Settings\Data\SettingsModel;
use Psr\Log\LoggerInterface;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class SettingsRestEndpoint
 *
 * Handles REST API endpoints for managing PayPal settings (Settings Tab).
 */
class SettingsRestEndpoint extends RestEndpoint {

	/**
	 * The REST API endpoint base.
	 *
	 * @var string
	 */
	private const ENDPOINT = 'settings';

	/**
	 * The REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3/wc_paypal';

	/**
	 * The settings model instance.
	 *
	 * @var SettingsModel
	 */
	private SettingsModel $settings;

	/**
	 * The logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * SettingsRestEndpoint constructor.
	 *
	 * @param SettingsModel   $settings The settings model instance.
	 * @param LoggerInterface $logger   The logger instance.
	 */
	public function __construct(
		SettingsModel $settings,
		LoggerInterface $logger
	) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Registers the REST API routes for settings management.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . self::ENDPOINT,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Retrieves the current settings.
	 *
	 * @param WP_REST_Request $request The request instance.
	 * @return WP_REST_Response The response containing settings data or error details.
	 * @throws \Exception When encoding settings data fails.
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		try {
			// Get settings data.
			$data = $this->settings->get();

			// Ensure the data is JSON-encodable.
			$encoded = wp_json_encode( $data );
			if ( $encoded === false ) {
				throw new \Exception( 'Failed to encode settings data: ' . json_last_error_msg() );
			}

			// Create response with pre-verified JSON data.
			$response_data = array(
				'success' => true,
				'data'    => json_decode( $encoded, true ),
			);

			return new WP_REST_Response( $response_data, 200 );
		} catch ( \Exception $error ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $error->getMessage(),
				),
				500
			);
		}
	}

	/**
	 * Updates the settings with provided data.
	 *
	 * @param WP_REST_Request $request The request instance containing new settings.
	 * @return WP_REST_Response The response containing updated settings or error details.
	 * @throws \Exception When encoding updated settings fails.
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		try {
			$data = $request->get_json_params();
			$this->settings->update( $data );
			$updated_data = $this->settings->get();

			// Verify JSON encoding.
			$encoded = wp_json_encode( $updated_data );
			if ( $encoded === false ) {
				throw new \Exception( 'Failed to encode updated settings: ' . json_last_error_msg() );
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => json_decode( $encoded, true ),
				),
				200
			);
		} catch ( \Exception $error ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $error->getMessage(),
				),
				500
			);
		}
	}
}
