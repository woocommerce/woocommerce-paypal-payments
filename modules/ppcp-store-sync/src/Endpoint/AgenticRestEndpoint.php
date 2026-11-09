<?php

/**
 * Base class for all agentic commerce REST endpoints.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Endpoint;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use JsonException;
use WC_REST_Controller;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\StoreSync\Errors\Http\InternalServerError;
use WooCommerce\PayPalCommerce\StoreSync\Errors\Http\NotFoundError;
use WooCommerce\PayPalCommerce\StoreSync\Errors\AgenticError;
use WooCommerce\PayPalCommerce\StoreSync\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\StoreSync\Response\CartResponse;
use WooCommerce\PayPalCommerce\StoreSync\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\StoreSync\Session\AgenticSessionHandler;
use WooCommerce\PayPalCommerce\StoreSync\CartValidation\CartValidationProcessor;
use WooCommerce\PayPalCommerce\StoreSync\Helper\PayPalOrderManager;
use WooCommerce\PayPalCommerce\StoreSync\Helper\AgenticSessionManager;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StoreData;
use WooCommerce\PayPalCommerce\StoreSync\StoreData\StorePayPalCart;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
/**
 * Base class for REST controllers in the agentic commerce module.
 */
abstract class AgenticRestEndpoint extends WC_REST_Controller
{
    /**
     * Endpoint namespace.
     */
    public const NAMESPACE = 'wc/v3/agentic';
    /**
     * JWT scope(s) required for the endpoint.
     */
    protected const REQUIRED_SCOPES = array('cart');
    protected const ACTION_NAME_SUCCESS = 'woocommerce_paypal_payments_store_sync';
    protected const ACTION_NAME_ERROR = 'woocommerce_paypal_payments_store_sync_error';
    private AuthServiceProvider $auth_provider;
    private AgenticSessionHandler $session_handler;
    private AgenticSessionManager $session_manager;
    protected ResponseFactory $response_factory;
    protected CartValidationProcessor $validation_processor;
    protected LoggerInterface $logger;
    protected PayPalOrderManager $order_manager;
    protected StoreValidation $validation;
    protected StoreData $store_data;
    public function __construct(AuthServiceProvider $auth_provider, AgenticSessionHandler $session_handler, AgenticSessionManager $session_manager, ResponseFactory $response_factory, CartValidationProcessor $validation_processor, LoggerInterface $logger, PayPalOrderManager $order_manager, StoreData $store_data)
    {
        $this->auth_provider = $auth_provider;
        $this->session_handler = $session_handler;
        $this->session_manager = $session_manager;
        $this->response_factory = $response_factory;
        $this->validation_processor = $validation_processor;
        $this->logger = $logger;
        $this->order_manager = $order_manager;
        $this->store_data = $store_data;
        $this->validation = new StoreValidation();
    }
    /**
     * Verify JWT access.
     *
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error True if access is granted, otherwise a WP_Error object.
     */
    public function check_permission(WP_REST_Request $request)
    {
        $token = $request->get_header('Authorization');
        $auth_service = $this->auth_provider->auth_service();
        $context = $auth_service->get_token($token);
        if (is_wp_error($context)) {
            $this->logger->error('[REST] Permission denied', $context->get_all_error_data());
            return $context;
        }
        return $auth_service->verify_claims($context, static::REQUIRED_SCOPES);
    }
    /**
     * @return mixed
     */
    protected function with_session(callable $callback)
    {
        return $this->session_manager->with_session($callback);
    }
    /**
     * Successful API response, always returns cart details.
     *
     * @param CartResponse $cart        The PayPalCart response object.
     * @param int          $status_code HTTP status code.
     * @return WP_REST_Response The successful response.
     */
    protected function cart_details(CartResponse $cart, int $status_code): WP_REST_Response
    {
        $this->logger->info("[REST] {$status_code} Response", $cart->to_array());
        do_action(static::ACTION_NAME_SUCCESS, $cart->cart_id(), $cart->store_cart(), $status_code);
        return new WP_REST_Response($cart->to_array(), $status_code);
    }
    /**
     * Returns an error REST API response.
     *
     * @param AgenticError $error The error object.
     * @return WP_REST_Response The error response.
     */
    protected function error(AgenticError $error): WP_REST_Response
    {
        $error_id = $error->get_debug_id();
        $this->logger->error("[REST] Error - {$error_id}", $error->to_array());
        do_action(static::ACTION_NAME_ERROR, $error);
        return new WP_REST_Response($error->to_array(), $error->get_status_code());
    }
    /**
     * Parses and validates JSON request body.
     *
     * @param WP_REST_Request $request The request object.
     * @return array|AgenticError Parsed data or error response.
     */
    protected function parse_json_body(WP_REST_Request $request)
    {
        $body = $request->get_body();
        if (empty($body)) {
            return new InternalServerError('Request body is required');
        }
        try {
            return json_decode($body, \true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return new InternalServerError('Request body contains invalid JSON. Error: ' . $exception->getMessage());
        }
    }
    /**
     * Parse and validate cart from request body.
     *
     * @param WP_REST_Request $request The request object.
     * @return StorePayPalCart|AgenticError Enriched cart or error.
     */
    protected function get_cart_from_request(WP_REST_Request $request)
    {
        $data = $this->parse_json_body($request);
        if ($data instanceof AgenticError) {
            return $data;
        }
        $this->validation = new StoreValidation();
        $paypal_cart = PayPalCart::from_array($data, $this->validation);
        $store_cart = $this->store_data->create_cart($paypal_cart, $this->validation);
        $this->validation_processor->validate_cart($store_cart);
        return $store_cart;
    }
    /**
     * Load cart data from local storage (ie from session table) with standardized error handling.
     *
     * @param string $cart_id The cart ID to load.
     * @return array|AgenticError Cart session data or error.
     */
    protected function get_stored_cart(string $cart_id)
    {
        $session = $this->session_handler->load_cart_session($cart_id);
        if (!$session) {
            return new NotFoundError("Cart with ID '{$cart_id}' does not exist or has expired", array(array('field' => 'cartId', 'issue' => 'NOT_FOUND', 'description' => "Cart with ID '{$cart_id}' does not exist. Verify cart ID or create a new cart.")));
        }
        return $session;
    }
    protected function create_local_cart(PayPalCart $cart, string $ec_token): string
    {
        return $this->session_handler->create_cart_session($cart, $ec_token);
    }
    protected function store_local_cart(string $cart_id, PayPalCart $cart, ?string $ec_token = null): bool
    {
        return $this->session_handler->update_cart_session($cart_id, $cart, $ec_token);
    }
    protected function flush_local_cart(string $cart_id): bool
    {
        return $this->session_handler->destroy_cart_session($cart_id);
    }
    /**
     * Get standard cart ID argument definition for route registration.
     *
     * @return array Cart ID argument configuration.
     */
    protected function get_cart_id_arg(): array
    {
        return array('required' => \true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'validate_callback' => fn($param) => $this->validate_cart_id($param));
    }
    /**
     * Standard cart ID validation callback.
     *
     * @param mixed $param The parameter to validate.
     * @return bool True if valid cart ID format.
     */
    private function validate_cart_id($param): bool
    {
        return is_string($param) && strlen($param) >= 10;
    }
}
