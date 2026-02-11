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
/**
 * Class GetOrderEndpoint
 */
class GetOrderEndpoint implements \WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface
{
    public const ENDPOINT = 'ppc-get-order';
    private \WooCommerce\PayPalCommerce\Button\Endpoint\RequestData $request_data;
    private OrderEndpoint $api_endpoint;
    private LoggerInterface $logger;
    public function __construct(\WooCommerce\PayPalCommerce\Button\Endpoint\RequestData $request_data, OrderEndpoint $order_endpoint, LoggerInterface $logger)
    {
        $this->request_data = $request_data;
        $this->api_endpoint = $order_endpoint;
        $this->logger = $logger;
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
