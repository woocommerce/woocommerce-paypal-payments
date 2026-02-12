<?php

/**
 * The endpoint to get a PayPal order.
 *
 * @package WooCommerce\PayPalCommerce\Button\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Session\CartDataTransientStorage;
/**
 * Class GetOrderEndpoint
 */
class GetOrderEndpoint implements \WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface
{
    public const ENDPOINT = 'ppc-get-order';
    private \WooCommerce\PayPalCommerce\Button\Endpoint\RequestData $request_data;
    private OrderEndpoint $api_endpoint;
    private LoggerInterface $logger;
    private CartDataTransientStorage $cart_data_storage;
    public function __construct(\WooCommerce\PayPalCommerce\Button\Endpoint\RequestData $request_data, OrderEndpoint $order_endpoint, LoggerInterface $logger, CartDataTransientStorage $cart_data_storage)
    {
        $this->request_data = $request_data;
        $this->api_endpoint = $order_endpoint;
        $this->logger = $logger;
        $this->cart_data_storage = $cart_data_storage;
    }
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    public function handle_request(): bool
    {
        try {
            $data = $this->request_data->read_request($this->nonce());
            $order_id = $data['order_id'] ?? '';
            if (empty($order_id)) {
                wp_send_json_error(array('message' => __('Order ID is required', 'woocommerce-paypal-payments')));
                return \false;
            }
            // Security: Verify that CartData transient exists for this PayPal order ID.
            // We cannot rely on session data (lost in cross-browser AppSwitch flows)
            // or query/hash parameters (technical limitations). Instead, we verify
            // a CartData transient exists for this order, which indicates it was
            // created recently through our system and serves as a layer of protection
            // against unauthorized access to order details.
            if (!$this->cart_data_storage->get_by_paypal_order_id($order_id)) {
                $this->logger->warning(sprintf('Unauthorized GetOrder attempt for PayPal order %s. No CartData found.', $order_id));
                wp_send_json_error(array('message' => __('Invalid or expired order access', 'woocommerce-paypal-payments')));
                return \false;
            }
            $order = $this->api_endpoint->order($order_id);
            wp_send_json_success($order->to_array());
            return \true;
        } catch (RuntimeException $error) {
            $this->logger->error('Get order failed: ' . $error->getMessage());
            wp_send_json_error(array('name' => is_a($error, PayPalApiException::class) ? $error->name() : '', 'message' => $error->getMessage(), 'code' => $error->getCode(), 'details' => is_a($error, PayPalApiException::class) ? $error->details() : array()));
        } catch (Exception $exception) {
            $this->logger->error('Get order failed: ' . $exception->getMessage());
            wp_send_json_error(array('message' => $exception->getMessage()));
        }
        return \false;
    }
}
