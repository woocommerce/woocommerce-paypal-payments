<?php

/**
 * CompleteOnClickEndpoint class file.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
/**
 * Handles the REST endpoint for marking todos as completed on click.
 *
 * This file is part of the WooCommerce PayPal Commerce Settings module and provides
 * functionality for tracking which todos have been completed via click actions.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WooCommerce\PayPalCommerce\Settings\Data\TodosModel;
/**
 * Class CompleteOnClickEndpoint
 *
 * Handles REST API endpoints for marking todos as completed when clicked.
 * Extends the base RestEndpoint class to provide specific todo completion functionality.
 */
class CompleteOnClickEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    /**
     * The base URL for the REST endpoint.
     *
     * @var string
     */
    protected $rest_base = 'complete-onclick';
    /**
     * The todos model instance.
     *
     * @var TodosModel
     */
    protected TodosModel $todos;
    /**
     * Constructor.
     *
     * @param TodosModel $todos The todos model instance.
     */
    public function __construct(TodosModel $todos)
    {
        $this->todos = $todos;
    }
    /**
     * Registers the routes for the complete-onclick endpoint.
     *
     * Sets up the REST API route for handling todo completion via POST requests.
     *
     * @return void
     */
    public function register_routes(): void
    {
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'complete_onclick'), 'permission_callback' => array($this, 'check_permission')));
    }
    /**
     * Handles the completion of a todo item via click.
     *
     * Processes the POST request to mark a specific todo as completed,
     * updating the stored settings accordingly.
     *
     * @param WP_REST_Request $request The incoming REST request object.
     * @return WP_REST_Response The REST response indicating success or failure.
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
}
