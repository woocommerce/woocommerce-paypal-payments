<?php

/**
 * REST controller for authenticating a PayPal merchant.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WooCommerce\PayPalCommerce\Settings\Service\AuthenticationManager;
use WooCommerce\PayPalCommerce\Settings\Service\SettingsDataManager;
/**
 * REST controller for authenticating and connecting to a PayPal merchant account.
 *
 * This endpoint is responsible for verifying credentials and establishing
 * a connection, regardless of whether they are provided via:
 * 1. Direct login (clientId + secret)
 * 2. UI-driven login (sharedId + authCode)
 *
 * It handles the actual authentication process after the login URL has been used.
 */
class AuthenticationRestEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    /**
     * The base path for this REST controller.
     *
     * @var string
     */
    protected $rest_base = 'authenticate';
    /**
     * Authentication manager service.
     *
     * @var AuthenticationManager
     */
    private AuthenticationManager $authentication_manager;
    /**
     * Settings data manager service.
     *
     * @var SettingsDataManager
     */
    private SettingsDataManager $data_manager;
    /**
     * Defines the JSON response format (when connection was successful).
     *
     * @var array
     */
    private array $response_map = array('merchant_id' => array('js_name' => 'merchantId'), 'merchant_email' => array('js_name' => 'email'));
    /**
     * Constructor.
     *
     * @param AuthenticationManager $authentication_manager The authentication manager.
     * @param SettingsDataManager   $data_manager           Settings data manager, to reset
     *                                                      settings.
     */
    public function __construct(AuthenticationManager $authentication_manager, SettingsDataManager $data_manager)
    {
        $this->authentication_manager = $authentication_manager;
        $this->data_manager = $data_manager;
    }
    /**
     * Configure REST API routes.
     */
    public function register_routes(): void
    {
        /**
         * POST /wp-json/wc/v3/wc_paypal/authenticate/direct
         * {
         *     clientId
         *     clientSecret
         *     useSandbox
         * }
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base . '/direct', array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'connect_direct'), 'permission_callback' => array($this, 'check_permission'), 'args' => array('clientId' => array('required' => \true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'minLength' => 80, 'maxLength' => 80), 'clientSecret' => array('required' => \true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'), 'useSandbox' => array('required' => \false, 'type' => 'boolean', 'default' => \false, 'sanitize_callback' => array($this, 'to_boolean')))));
        /**
         * POST /wp-json/wc/v3/wc_paypal/authenticate/oauth
         * {
         *     sharedId
         *     authCode
         *     useSandbox
         * }
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base . '/oauth', array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'connect_oauth'), 'permission_callback' => array($this, 'check_permission'), 'args' => array('sharedId' => array('required' => \true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'), 'authCode' => array('required' => \true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'), 'useSandbox' => array('default' => 0, 'type' => 'boolean', 'sanitize_callback' => array($this, 'to_boolean')))));
        /**
         * POST /wp-json/wc/v3/wc_paypal/authenticate/disconnect
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base . '/disconnect', array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'disconnect'), 'permission_callback' => array($this, 'check_permission')));
    }
    /**
     * Direct login: Retrieves merchantId and email using clientId and clientSecret.
     *
     * This is the "Manual Login" logic, when a merchant already knows their
     * API credentials.
     *
     * @param WP_REST_Request $request Full data about the request.
     */
    public function connect_direct(WP_REST_Request $request): WP_REST_Response
    {
        $client_id = $request->get_param('clientId');
        $client_secret = $request->get_param('clientSecret');
        $use_sandbox = $request->get_param('useSandbox');
        try {
            $this->authentication_manager->validate_id_and_secret($client_id, $client_secret);
            $this->authentication_manager->authenticate_via_direct_api($use_sandbox, $client_id, $client_secret);
        } catch (Exception $exception) {
            return $this->return_error($exception->getMessage());
        }
        $account = $this->authentication_manager->get_account_details();
        $response = $this->sanitize_for_javascript($account, $this->response_map);
        return $this->return_success($response);
    }
    /**
     * OAuth login: Retrieves clientId and clientSecret using a sharedId and authCode.
     *
     * This is the final step in the UI-driven login via the OAuth popup, which
     * is triggered by the LoginLinkRestEndpoint URL.
     *
     * @param WP_REST_Request $request Full data about the request.
     */
    public function connect_oauth(WP_REST_Request $request): WP_REST_Response
    {
        $shared_id = $request->get_param('sharedId');
        $auth_code = $request->get_param('authCode');
        $use_sandbox = $request->get_param('useSandbox');
        $this->authentication_manager->remember_oauth_connection_details($shared_id, $auth_code, $use_sandbox);
        return $this->return_success(\true);
    }
    /**
     * Disconnect the merchant and clear the authentication details.
     *
     * @param WP_REST_Request $request Full data about the request.
     *
     * @return WP_REST_Response
     */
    public function disconnect(WP_REST_Request $request): WP_REST_Response
    {
        $reset_settings = $request->get_param('reset');
        $this->authentication_manager->disconnect();
        if ($reset_settings) {
            $this->data_manager->reset_all_settings();
        }
        return $this->return_success('OK');
    }
}
