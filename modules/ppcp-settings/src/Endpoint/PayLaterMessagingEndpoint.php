<?php

/**
 * REST endpoint to manage the Pay Later Messaging configurator page.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WooCommerce\PayPalCommerce\PayLaterConfigurator\Endpoint\SaveConfig;
use WooCommerce\PayPalCommerce\PayLaterConfigurator\Factory\ConfigFactory;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
/**
 * REST controller for the "Pay Later Messaging" settings tab.
 *
 * This API acts as the intermediary between the "external world" and our
 * internal data model.
 */
class PayLaterMessagingEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    /**
     * The base path for this REST controller.
     *
     * @var string
     */
    protected $rest_base = 'pay_later_messaging';
    /**
     * The settings.
     *
     * @var Settings
     */
    protected $settings;
    /**
     * Save config handler.
     *
     * @var SaveConfig
     */
    private $save_config;
    /**
     * PayLaterMessagingEndpoint constructor.
     *
     * @param Settings   $settings The settings.
     * @param SaveConfig $save_config Save config handler.
     */
    public function __construct(Settings $settings, SaveConfig $save_config)
    {
        $this->settings = $settings;
        $this->save_config = $save_config;
    }
    /**
     * Configure REST API routes.
     */
    public function register_routes(): void
    {
        /**
         * GET wc/v3/wc_paypal/pay_later_messaging
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array('methods' => WP_REST_Server::READABLE, 'callback' => array($this, 'get_details'), 'permission_callback' => array($this, 'check_permission')));
        /**
         * POST wc/v3/wc_paypal/pay_later_messaging
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'update_details'), 'permission_callback' => array($this, 'check_permission')));
    }
    /**
     * Returns Pay Later Messaging configuration details.
     *
     * @return WP_REST_Response The current payment methods details.
     */
    public function get_details(): WP_REST_Response
    {
        return $this->return_success((new ConfigFactory())->from_settings($this->settings));
    }
    /**
     * Updates Pay Later Messaging configuration details based on the request.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_REST_Response The updated Pay Later Messaging configuration details.
     */
    public function update_details(WP_REST_Request $request): WP_REST_Response
    {
        $this->save_config->save_config($request->get_json_params());
        return $this->get_details();
    }
}
