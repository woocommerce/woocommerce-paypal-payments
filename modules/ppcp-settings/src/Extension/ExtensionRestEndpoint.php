<?php

/**
 * Base REST endpoint for extension settings.
 *
 * Handles route registration, persistence, and standard responses.
 * Extensions only need to implement store_name() and sanitize_rest_data().
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Extension;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WooCommerce\PayPalCommerce\Settings\Data\AbstractDataModel;
use WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint;
/**
 * Class ExtensionRestEndpoint
 */
abstract class ExtensionRestEndpoint extends RestEndpoint
{
    /**
     * Extension must define the leaf name of the REST path!
     *
     * This path must match the value used in the extension's JS code.
     */
    protected const PATH = '';
    /**
     * The data model for persistence.
     *
     * @var AbstractDataModel
     */
    protected AbstractDataModel $data_model;
    /**
     * Constructor.
     *
     * @param AbstractDataModel $data_model The data model for persistence.
     */
    public function __construct(AbstractDataModel $data_model)
    {
        $this->data_model = $data_model;
    }
    /**
     * Sanitizes and validates REST request data.
     *
     * Return NULL to reject the request (no changes will be saved).
     * Return sanitized array to accept and persist the data.
     *
     * @param array $data Raw request data.
     * @return array|null Sanitized data or NULL to reject.
     */
    abstract protected function sanitize_rest_data(array $data): ?array;
    public function register_routes(): void
    {
        register_rest_route(static::NAMESPACE, '/ext/' . static::PATH, array(array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_settings'), 'permission_callback' => array($this, 'check_permission')), array('methods' => WP_REST_Server::CREATABLE, 'callback' => array($this, 'update_settings'), 'permission_callback' => array($this, 'check_permission'))));
    }
    public function get_settings(): WP_REST_Response
    {
        return $this->return_success($this->data_model->to_array());
    }
    public function update_settings(WP_REST_Request $request): WP_REST_Response
    {
        $raw_data = $request->get_params();
        $sanitized_data = $this->sanitize_rest_data($raw_data);
        // NULL means: reject the request, do not save.
        if (null === $sanitized_data) {
            return $this->return_error('Invalid data provided');
        }
        $this->data_model->from_array($sanitized_data);
        $this->data_model->save();
        return $this->get_settings();
    }
}
