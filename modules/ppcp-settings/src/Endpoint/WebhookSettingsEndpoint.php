<?php
/**
 * REST endpoint to manage the onboarding module.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\WebhookEndpoint;
use WooCommerce\PayPalCommerce\Webhooks\Status\WebhookSimulation;
use WooCommerce\PayPalCommerce\Webhooks\WebhookRegistrar;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class WebhookSettingsEndpoint
 *
 * Note: Endpoint for webhook related requests
 */
class WebhookSettingsEndpoint extends RestEndpoint {
	/**
	 * Endpoint base to fetch webhook settings and resubscribe
	 *
	 * @var string
	 */
	protected $rest_base = 'webhook_settings';

	/**
	 * Endpoint base to start webhook simulation and check the state
	 *
	 * @var string
	 */
	protected string $rest_simulate_base = 'webhook_simulate';

	/**
	 * Application webhook endpoint
	 *
	 * @var WebhookEndpoint
	 */
	private WebhookEndpoint $webhook_endpoint;

	/**
	 * A service that allows resubscribing webhooks
	 *
	 * @var WebhookRegistrar
	 */
	private WebhookRegistrar $webhook_registrar;

	/**
	 * A service that allows webhook simulations
	 *
	 * @var WebhookSimulation
	 */
	private WebhookSimulation $webhook_simulation;

	/**
	 * WebhookSettingsEndpoint constructor.
	 *
	 * @param WebhookEndpoint   $webhook_endpoint A list of subscribed webhooks and a webhook endpoint URL.
	 * @param WebhookRegistrar  $webhook_registrar A service that allows resubscribing webhooks.
	 * @param WebhookSimulation $webhook_simulation A service that allows webhook simulations.
	 */
	public function __construct( WebhookEndpoint $webhook_endpoint, WebhookRegistrar $webhook_registrar, WebhookSimulation $webhook_simulation ) {
		$this->webhook_endpoint   = $webhook_endpoint;
		$this->webhook_registrar  = $webhook_registrar;
		$this->webhook_simulation = $webhook_simulation;
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
					'callback'            => array( $this, 'get_webhooks' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'resubscribe_webhooks' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_simulate_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'check_simulated_webhook_state' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'simulate_webhooks_start' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Returns a webhook endpoint URL and list of subscribed webhooks
	 *
	 * @return WP_REST_Response
	 */
	public function get_webhooks(): WP_REST_Response {
		try {
			$webhook_list   = ( $this->webhook_endpoint->list() )[0];
			$webhook_events = array_map(
				static function ( stdClass $webhook ) {
					return strtolower( $webhook->name );
				},
				$webhook_list->event_types()
			);

			return $this->return_success(
				array(
					'webhooks' => array(
						'url'    => $webhook_list->url(),
						'events' => implode( ', ', $webhook_events ),
					),
				)
			);
		} catch ( \Exception $error ) {
			return $this->return_error( 'Problem while fetching webhooks data' );
		}
	}

	/**
	 * Re-subscribes webhooks and returns webhooks
	 *
	 * @return WP_REST_Response
	 */
	public function resubscribe_webhooks(): WP_REST_Response {
		if ( ! $this->webhook_registrar->register() ) {
			return $this->return_error( 'Webhook subscription failed.' );
		}
		return $this->get_webhooks();
	}

	/**
	 * Starts webhook simulation
	 *
	 * @return WP_REST_Response
	 */
	public function simulate_webhooks_start(): WP_REST_Response {
		try {
			$this->webhook_simulation->start();
			return $this->return_success( array() );
		} catch ( \Exception $error ) {
			return $this->return_error( $error->getMessage() );
		}
	}

	/**
	 * Checks webhook simulation state
	 *
	 * @return WP_REST_Response
	 */
	public function check_simulated_webhook_state(): WP_REST_Response {
		try {
			$state = $this->webhook_simulation->get_state();

			return $this->return_success(
				array(
					'state' => $state,
				)
			);

		} catch ( \Exception $error ) {
			return $this->return_error( $error->getMessage() );
		}
	}
}
