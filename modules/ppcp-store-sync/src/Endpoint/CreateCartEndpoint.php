<?php

/**
 * Create Cart Endpoint for Agentic Commerce.
 *
 * POST /api/paypal/v1/merchant-cart
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Endpoint;

use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\StoreSync\Errors\AgenticError;
/**
 * Create Cart REST endpoint.
 */
class CreateCartEndpoint extends \WooCommerce\PayPalCommerce\StoreSync\Endpoint\AgenticRestEndpoint
{
    /**
     * The endpoint path following PayPal specs.
     */
    private const PATH = 'merchant-cart';
    /**
     * The expected HTTP method.
     */
    private const METHOD = 'POST';
    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, self::PATH, array('methods' => self::METHOD, 'callback' => fn($request) => $this->with_session(fn() => $this->create_cart($request)), 'permission_callback' => fn($request) => $this->check_permission($request)));
    }
    /**
     * Create a new cart.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response The REST response.
     */
    public function create_cart(WP_REST_Request $request): WP_REST_Response
    {
        $store_cart = $this->get_cart_from_request($request);
        if ($store_cart instanceof AgenticError) {
            return $this->error($store_cart);
        }
        $paypal_cart = $store_cart->paypal_cart();
        // Token might be an empty string, when order creation fails. That's okay.
        $ec_token = $this->order_manager->create_order($paypal_cart);
        $cart_id = $this->create_local_cart($paypal_cart, $ec_token);
        $store_cart->set_paypal_order($ec_token);
        $response = $this->response_factory->new_cart($store_cart, $cart_id);
        return $this->cart_details($response, 201);
    }
}
