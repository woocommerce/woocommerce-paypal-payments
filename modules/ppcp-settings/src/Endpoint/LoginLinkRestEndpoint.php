<?php

/**
 * REST endpoint to manage the onboarding module.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Endpoint;

use WP_REST_Server;
use WP_REST_Response;
use WP_REST_Request;
use WooCommerce\PayPalCommerce\Settings\Service\ConnectionUrlGenerator;
/**
 * REST controller that generates merchant login URLs for PayPal.
 *
 * This endpoint is responsible solely for generating a URL that initiates
 * the PayPal login flow. It does not handle the authentication itself.
 *
 * The generated URL is typically used to redirect merchants to PayPal's login page.
 * After successful login, the authentication process is completed via the
 * AuthenticationRestEndpoint.
 */
class LoginLinkRestEndpoint extends \WooCommerce\PayPalCommerce\Settings\Endpoint\RestEndpoint
{
    /**
     * The base path for this REST controller.
     *
     * @var string
     */
    protected $rest_base = 'login_link';
    /**
     * Login-URL generator.
     *
     * @var ConnectionUrlGenerator
     */
    protected ConnectionUrlGenerator $url_generator;
    /**
     * Constructor.
     *
     * @param ConnectionUrlGenerator $url_generator Login-URL generator.
     */
    public function __construct(ConnectionUrlGenerator $url_generator)
    {
        $this->url_generator = $url_generator;
    }
    /**
     * Configure REST API routes.
     */
    public function register_routes(): void
    {
        /**
         * POST /wp-json/wc/v3/wc_paypal/login_link
         * {
         *     useSandbox
         *     products
         * }
         */
        register_rest_route(static::NAMESPACE, '/' . $this->rest_base, array('methods' => WP_REST_Server::EDITABLE, 'callback' => array($this, 'get_login_url'), 'permission_callback' => array($this, 'check_permission'), 'args' => array('useSandbox' => array('default' => 0, 'type' => 'boolean', 'sanitize_callback' => array($this, 'to_boolean')), 'products' => array('required' => \true, 'type' => 'array', 'items' => array('type' => 'string'), 'sanitize_callback' => function ($products) {
            return array_map('sanitize_text_field', $products);
        }), 'options' => array('requires' => \false, 'type' => 'array', 'items' => array('type' => 'bool'), 'sanitize_callback' => function ($flags) {
            return array_map(array($this, 'to_boolean'), $flags);
        }))));
    }
    /**
     * Returns the full login URL for the requested environment and products.
     *
     * @param WP_REST_Request $request The request object.
     *
     * @return WP_REST_Response The login URL or an error response.
     */
    public function get_login_url(WP_REST_Request $request): WP_REST_Response
    {
        $use_sandbox = $request->get_param('useSandbox');
        $products = $request->get_param('products');
        $flags = (array) $request->get_param('options');
        try {
            $url = $this->url_generator->generate($products, $flags, $use_sandbox);
            return $this->return_success($url);
        } catch (\Exception $e) {
            return $this->return_error($e->getMessage());
        }
    }
}
