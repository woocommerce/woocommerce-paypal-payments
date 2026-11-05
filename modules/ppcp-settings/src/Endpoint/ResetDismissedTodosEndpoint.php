<?php

/**
 * REST endpoint to reset dismissed things to do items.
 *
 * Provides endpoint for resetting all dismissed todos
 * via WP REST API route.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WooCommerce\PayPalCommerce\Settings\Data\TodosModel;
/**
 * Class ResetDismissedTodosEndpoint
 *
 * Handles REST API endpoint for resetting dismissed things to do items.
 */
class ResetDismissedTodosEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    /**
     * The base path for this REST controller.
     *
     * @var string
     */
    protected $rest_base = 'reset-dismissed-todos';
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
     * Registers the REST API route for resetting todos.
     */
    public function register_routes(): void
    {
        /**
         * POST wc/v3/wc_paypal/reset-dismissed-todos
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'reset_dismissed_todos'), 'permission_callback' => array($this, 'check_permission')));
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
