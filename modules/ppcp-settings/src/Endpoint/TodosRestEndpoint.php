<?php
/**
 * REST endpoint to manage the things to do items.
 *
 * Provides endpoints for retrieving, updating, and resetting todos
 * via WP REST API routes.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WooCommerce\PayPalCommerce\Settings\Data\TodosModel;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\TodosDefinition;

/**
 * REST controller for the "Things To Do" items in the Overview tab.
 *
 * This API acts as the intermediary between the "external world" and our
 * internal data model. It's responsible for checking eligibility and
 * providing configuration data for things to do next.
 */
class TodosRestEndpoint extends RestEndpoint {
	/**
	 * The base path for this REST controller.
	 *
	 * @var string
	 */
	protected $rest_base = 'todos';

	/**
	 * The todos model instance.
	 *
	 * @var TodosModel
	 */
	protected TodosModel $todos;

	/**
	 * The todos definition instance.
	 *
	 * @var TodosDefinition
	 */
	protected TodosDefinition $todos_definition;

	/**
	 * The settings endpoint instance.
	 *
	 * @var SettingsRestEndpoint
	 */
	protected SettingsRestEndpoint $settings;

	/**
	 * TodosRestEndpoint constructor.
	 *
	 * @param TodosModel           $todos The todos model instance.
	 * @param TodosDefinition      $todos_definition The todos definition instance.
	 * @param SettingsRestEndpoint $settings The settings endpoint instance.
	 */
	public function __construct(
		TodosModel $todos,
		TodosDefinition $todos_definition,
		SettingsRestEndpoint $settings
	) {
		$this->todos            = $todos;
		$this->todos_definition = $todos_definition;
		$this->settings         = $settings;
	}

	/**
	 * Registers the REST API routes for todos management.
	 */
	public function register_routes(): void {
		/**
		 * GET wc/v3/wc_paypal/todos
		 * POST wc/v3/wc_paypal/todos
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_todos' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_todos' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Retrieves the current todos.
	 *
	 * @return WP_REST_Response The response containing todos data.
	 */
	public function get_todos(): WP_REST_Response {
		$settings              = get_option( 'ppcp-settings', array() );
		$dismissed_ids         = $settings['dismissedTodos'] ?? array();
		$completed_onclick_ids = $settings['completedOnClickTodos'] ?? array();

		$todos = array();
		foreach ( $this->todos_definition->get() as $id => $todo ) {
			// Skip if todo has completeOnClick flag and is in completed list.
			if (
				in_array( $id, $completed_onclick_ids, true ) &&
				isset( $todo['action']['completeOnClick'] ) &&
				$todo['action']['completeOnClick'] === true
			) {
				continue;
			}

			// Check eligibility and add to todos if eligible.
			if ( $todo['isEligible']() ) {
				$todos[] = array_merge(
					array( 'id' => $id ),
					array_diff_key( $todo, array( 'isEligible' => true ) )
				);
			}
		}

		return $this->return_success(
			array(
				'todos'                 => $todos,
				'dismissedTodos'        => $dismissed_ids,
				'completedOnClickTodos' => $completed_onclick_ids,
			)
		);
	}

	/**
	 * Updates the todos with provided data.
	 *
	 * @param WP_REST_Request $request The request instance containing todo updates.
	 * @return WP_REST_Response The response containing updated todos or error details.
	 */
	public function update_todos( WP_REST_Request $request ): WP_REST_Response {
		$data     = $request->get_json_params();
		$settings = get_option( 'ppcp-settings', array() );

		if ( isset( $data['dismissedTodos'] ) ) {
			$settings['dismissedTodos'] = array_unique(
				is_array( $data['dismissedTodos'] ) ? $data['dismissedTodos'] : array()
			);

			$update_result = update_option( 'ppcp-settings', $settings );

			if ( $update_result ) {
				return $this->return_success( $settings );
			}
		}

		return $this->return_success( $data );
	}
}
