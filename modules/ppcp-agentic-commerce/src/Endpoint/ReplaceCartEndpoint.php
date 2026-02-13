<?php

/**
 * Replace Cart Endpoint for Agentic Commerce.
 *
 * PUT /api/paypal/v1/merchant-cart/{cart_id}
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use RuntimeException;
use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\AgenticCommerce\Auth\AuthServiceProvider;
use WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CartValidationProcessor;
use WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation\CouponValidator\AppliedCouponsBuilder;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\Http\NotFoundError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Errors\AgenticError;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\AgenticSessionManager;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\PayPalOrderManager;
use WooCommerce\PayPalCommerce\AgenticCommerce\Response\ResponseFactory;
use WooCommerce\PayPalCommerce\AgenticCommerce\Session\AgenticSessionHandler;
/**
 * Replace Cart REST endpoint.
 *
 * Fully replaces an existing cart while preserving the payment token.
 */
class ReplaceCartEndpoint extends \WooCommerce\PayPalCommerce\AgenticCommerce\Endpoint\AgenticRestEndpoint
{
    /**
     * The endpoint path following PayPal specs.
     */
    private const PATH = 'merchant-cart/(?P<cart_id>[a-zA-Z0-9_-]+)';
    /**
     * The expected HTTP method.
     */
    private const METHOD = 'PUT';
    /**
     * Applied coupons builder for discount calculation.
     *
     * @var AppliedCouponsBuilder
     */
    private AppliedCouponsBuilder $applied_coupons_builder;
    /**
     * Constructor.
     *
     * @param AuthServiceProvider     $auth_provider Auth service provider.
     * @param AgenticSessionHandler   $session_handler Session handler.
     * @param AgenticSessionManager   $session_manager Session manager.
     * @param ResponseFactory         $response_factory Response factory.
     * @param CartValidationProcessor $validation_processor Validation processor.
     * @param LoggerInterface         $logger Logger.
     * @param PayPalOrderManager      $order_manager PayPal order manager.
     * @param AppliedCouponsBuilder   $applied_coupons_builder Applied coupons builder.
     */
    public function __construct(AuthServiceProvider $auth_provider, AgenticSessionHandler $session_handler, AgenticSessionManager $session_manager, ResponseFactory $response_factory, CartValidationProcessor $validation_processor, LoggerInterface $logger, PayPalOrderManager $order_manager, AppliedCouponsBuilder $applied_coupons_builder)
    {
        parent::__construct($auth_provider, $session_handler, $session_manager, $response_factory, $validation_processor, $logger, $order_manager);
        $this->applied_coupons_builder = $applied_coupons_builder;
    }
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
     * @param WP_REST_Request $request The REST request.
     * @return WP_REST_Response The REST response.
     */
    public function replace_cart(WP_REST_Request $request): WP_REST_Response
    {
        $cart_id = $request->get_param('cart_id');
        // Verify cart exists.
        $session = $this->get_stored_cart($cart_id);
        if ($session instanceof AgenticError) {
            return $this->error($session);
        }
        $new_cart = $this->get_cart_from_request($request);
        if ($new_cart instanceof AgenticError) {
            return $this->error($new_cart);
        }
        // Get the PayPal Order ID (ec_token).
        $paypal_order_id = $session['ec_token'];
        // Calculate total discount from applied coupons for PayPal order update.
        $total_discount = $this->applied_coupons_builder->calculate_total_discount($new_cart);
        // Update the PayPal Order with new totals (including discount).
        try {
            $this->order_manager->update_order($paypal_order_id, $new_cart, $total_discount);
        } catch (RuntimeException $e) {
            return $this->error_not_found('Failed to update PayPal Order: ' . $e->getMessage(), array('issue' => 'PAYPAL_ORDER_UPDATE_FAILED', 'description' => 'Could not synchronize cart changes with PayPal.'));
        }
        // Replace the cart session (preserving ec_token).
        $update_result = $this->store_local_cart($cart_id, $new_cart);
        if (!$update_result) {
            return $this->error_not_found('Failed to replace cart', array('issue' => 'CART_REPLACE_FAILED', 'description' => 'Cart replacement operation failed.'));
        }
        $response = $this->response_factory->from_cart($new_cart, $cart_id);
        return $this->cart_details($response, 200);
    }
    private function error_not_found(string $message, array $details): WP_REST_Response
    {
        return $this->error(new NotFoundError($message, array($details)));
    }
}
