<?php

/**
 * REST endpoint to manage the things to do items.
 *
 * Provides endpoints for retrieving, updating, completing, resetting, and managing todos
 * via WP REST API routes.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WooCommerce\PayPalCommerce\Settings\Data\TodosModel;
use WooCommerce\PayPalCommerce\Settings\Data\Definition\TodosDefinition;
use WooCommerce\PayPalCommerce\Settings\Service\TodosSortingAndFilteringService;
/**
 * REST controller for the "Things To Do" items in the Overview tab.
 *
 * This API acts as the intermediary between the "external world" and our
 * internal data model. It's responsible for checking eligibility and
 * providing configuration data for things to do next.
 */
class TodosRestEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
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
    protected \WooCommerce\PayPalCommerce\Settings\Endpoint\SettingsRestEndpoint $settings;
    /**
     * The todos sorting service.
     *
     * @var TodosSortingAndFilteringService
     */
    protected TodosSortingAndFilteringService $sorting_service;
    /**
     * TodosRestEndpoint constructor.
     *
     * @param TodosModel                      $todos The todos model instance.
     * @param TodosDefinition                 $todos_definition The todos definition instance.
     * @param SettingsRestEndpoint            $settings The settings endpoint instance.
     * @param TodosSortingAndFilteringService $sorting_service The todos sorting service.
     */
    public function __construct(TodosModel $todos, TodosDefinition $todos_definition, \WooCommerce\PayPalCommerce\Settings\Endpoint\SettingsRestEndpoint $settings, TodosSortingAndFilteringService $sorting_service)
    {
        $this->todos = $todos;
        $this->todos_definition = $todos_definition;
        $this->settings = $settings;
        $this->sorting_service = $sorting_service;
    }
    /**
     * Registers the REST API routes for todos management.
     */
    public function register_routes(): void
    {
        // GET/POST /todos - Get todos list and update dismissed todos.
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array(array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_todos'), 'permission_callback' => array($this, 'check_permission')), array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'update_todos'), 'permission_callback' => array($this, 'check_permission'))));
        // POST /todos/reset - Reset dismissed todos.
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base . '/reset', array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'reset_dismissed_todos'), 'permission_callback' => array($this, 'check_permission')));
        // POST /todos/complete - Mark todo as completed on click.
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base . '/complete', array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'complete_onclick'), 'permission_callback' => array($this, 'check_permission')));
    }
    /**
     * Retrieves the current todos.
     *
     * @return WP_REST_Response The response containing todos data.
     */
    public function get_todos(): WP_REST_Response
    {
        $todos_data = $this->todos->get_todos_data();
        $dismissed_ids = $todos_data['dismissedTodos'];
        $completed_onclick_ids = $todos_data['completedOnClickTodos'];
        $todos = array();
        foreach ($this->todos_definition->get() as $id => $todo) {
            // Skip if todo has completeOnClick flag and is in completed list.
            if (in_array($id, $completed_onclick_ids, \true) && isset($todo['action']['completeOnClick']) && $todo['action']['completeOnClick'] === \true) {
                continue;
            }
            // Check eligibility and add to todos if eligible.
            if ($todo['isEligible']()) {
                $todos[] = array_merge(array('id' => $id), array_diff_key($todo, array('isEligible' => \true)));
            }
        }
        $filtered_todos = $this->sorting_service->apply_all_priority_filters($todos);
        return $this->return_success(array('todos' => $filtered_todos, 'dismissedTodos' => $dismissed_ids, 'completedOnClickTodos' => $completed_onclick_ids));
    }
    /**
     * Updates the todos with provided data.
     *
     * @param WP_REST_Request $request The request instance containing todo updates.
     * @return WP_REST_Response The response containing updated todos or error details.
     */
    public function update_todos(WP_REST_Request $request): WP_REST_Response
    {
        $data = $request->get_json_params();
        if (isset($data['dismissedTodos'])) {
            try {
                $dismissed_todos = is_array($data['dismissedTodos']) ? $data['dismissedTodos'] : array();
                $this->todos->update_dismissed_todos($dismissed_todos);
                return $this->return_success($this->todos->get_todos_data());
            } catch (\Exception $e) {
                return $this->return_error($e->getMessage());
            }
        }
        return $this->return_success($data);
    }
    /**
     * Handles the completion of a todo item via click.
     *
     * @param WP_REST_Request $request The request instance.
     * @return WP_REST_Response The response containing completion status.
     */
    public function complete_onclick(WP_REST_Request $request): WP_REST_Response
    {
        $todo_id = $request->get_param('todoId');
        if (!$todo_id) {
            return $this->return_error(__('Todo ID is required.', 'woocommerce-paypal-payments'));
        }
        try {
            $todos_data = $this->todos->get_todos_data();
            $completed_todos = $todos_data['completedOnClickTodos'];
            if (!in_array($todo_id, $completed_todos, \true)) {
                $this->todos->update_completed_onclick_todos(array_merge($completed_todos, array($todo_id)));
            }
            return $this->return_success(array('message' => __('Todo marked as completed on click successfully.', 'woocommerce-paypal-payments'), 'todoId' => $todo_id));
        } catch (\Exception $e) {
            return $this->return_error(__('Failed to mark todo as completed on click.', 'woocommerce-paypal-payments'));
        }
    }
    /**
     * Resets all dismissed todos.
     *
     * @param WP_REST_Request $request The request instance.
     * @return WP_REST_Response The response containing reset status.
     */
    public function reset_dismissed_todos(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $this->todos->reset_dismissed_todos();
            return $this->return_success(array('message' => __('Dismissed todos reset successfully.', 'woocommerce-paypal-payments')));
        } catch (\Exception $e) {
            return $this->return_error(__('Failed to reset dismissed todos.', 'woocommerce-paypal-payments'));
        }
    }
}
