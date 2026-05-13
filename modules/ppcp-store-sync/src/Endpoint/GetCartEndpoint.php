<?php

/**
 * Get Cart Endpoint for Agentic Commerce.
 *
 * GET /api/paypal/v1/merchant-cart/{cart_id}
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Endpoint;

use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\StoreSync\Errors\AgenticError;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
/**
 * Get Cart REST endpoint.
 */
class GetCartEndpoint extends \WooCommerce\PayPalCommerce\StoreSync\Endpoint\AgenticRestEndpoint
{
    /**
     * The endpoint path following PayPal specs.
     */
    private const PATH = 'merchant-cart/(?P<cart_id>[a-zA-Z0-9_-]+)';
    /**
     * The expected HTTP method.
     */
    private const METHOD = 'GET';
    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, self::PATH, array('methods' => self::METHOD, 'callback' => fn($request) => $this->with_session(fn() => $this->get_cart($request)), 'permission_callback' => fn($request) => $this->check_permission($request), 'args' => array('cart_id' => $this->get_cart_id_arg())));
    }
    /**
     * Get an existing cart.
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response The REST response.
     */
    public function get_cart(WP_REST_Request $request): WP_REST_Response
    {
        $cart_id = $request->get_param('cart_id');
        $session = $this->get_stored_cart($cart_id);
        if ($session instanceof AgenticError) {
            return $this->error($session);
        }
        // TODO: Validation issues from re-parsing are discarded; will be cleaned up in a future refactor.
        $store_cart = $this->store_data->create_cart($session['cart'], new StoreValidation());
        $store_cart->set_paypal_order($session['ec_token']);
        $response = $this->response_factory->from_cart($store_cart, $cart_id);
        return $this->cart_details($response, 200);
    }
}
