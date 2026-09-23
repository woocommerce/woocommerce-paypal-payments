<?php

/**
 * Replace Cart Endpoint for Agentic Commerce.
 *
 * PUT /api/paypal/v1/merchant-cart/{cart_id}
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Endpoint;

use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\StoreSync\Errors\Http\NotFoundError;
use WooCommerce\PayPalCommerce\StoreSync\Errors\AgenticError;
/**
 * Replace Cart REST endpoint.
 *
 * Fully replaces an existing cart while preserving the payment token.
 */
class ReplaceCartEndpoint extends \WooCommerce\PayPalCommerce\StoreSync\Endpoint\AgenticRestEndpoint
{
    /**
     * The endpoint path following PayPal specs.
     */
    private const PATH = 'merchant-cart/(?P<cart_id>[a-zA-Z0-9_-]+)';
    /**
     * The expected HTTP method.
     */
    private const METHOD = 'PUT';
    protected const ACTION_NAME_SUCCESS = 'woocommerce_paypal_payments_store_sync_replace';
    protected const ACTION_NAME_ERROR = 'woocommerce_paypal_payments_store_sync_replace_error';
    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, self::PATH, array('methods' => self::METHOD, 'callback' => fn($request) => $this->with_session(fn() => $this->replace_cart($request)), 'permission_callback' => fn($request) => $this->check_permission($request), 'args' => array('cart_id' => $this->get_cart_id_arg())));
    }
    /**
     * Replace an existing cart with new data.
     *
     * Per PayPal specs, the PUT endpoint may create a new PayPal order when:
     * - The cart doesn't have an ec_token yet (POST validation failed)
     * - The existing ec_token has expired
     *
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response The REST response.
     */
    public function replace_cart(WP_REST_Request $request): WP_REST_Response
    {
        $cart_id = $request->get_param('cart_id');
        // Verify cart exists and get current session data.
        $session = $this->get_stored_cart($cart_id);
        if ($session instanceof AgenticError) {
            return $this->error($session);
        }
        $store_cart = $this->get_cart_from_request($request);
        if ($store_cart instanceof AgenticError) {
            return $this->error($store_cart);
        }
        $new_token = null;
        $store_cart->set_paypal_order($session['ec_token']);
        // Determine if we need to create a new PayPal order.
        if (empty($session['ec_token']) && $store_cart->validation()->is_empty()) {
            $new_token = $this->order_manager->create_order($store_cart->paypal_cart()) ?: null;
            $this->logger->info('[REST] PUT created new PayPal order', array('cart_id' => $cart_id, 'new_token' => $new_token ?? '(none - order creation failed)'));
            if ($new_token) {
                $store_cart->set_paypal_order($new_token);
            }
        }
        // Update the cart session, passing new token when one was created.
        $update_result = $this->store_local_cart($cart_id, $store_cart->paypal_cart(), $new_token);
        if (!$update_result) {
            return $this->error_not_found('Failed to replace cart', array('issue' => 'CART_REPLACE_FAILED', 'description' => 'Cart replacement operation failed.'));
        }
        $response = $this->response_factory->from_cart($store_cart, $cart_id);
        return $this->cart_details($response, 200);
    }
    private function error_not_found(string $message, array $details): WP_REST_Response
    {
        return $this->error(new NotFoundError($message, array($details)));
    }
}
