<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\StoreApi\Endpoint;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Entity\CartResponse;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\CartFactory;
/**
 * The wrapper for the WC Store API cart requests.
 */
class CartEndpoint
{
    use RequestTrait;
    private CartFactory $cart_factory;
    private LoggerInterface $logger;
    /**
     * Unused (for trait).
     */
    private string $host = '';
    public function __construct(CartFactory $cart_factory, LoggerInterface $logger)
    {
        $this->cart_factory = $cart_factory;
        $this->logger = $logger;
    }
    /**
     * Returns the cart of the current user (based on the current cookies).
     *
     * @throws Exception When request fails.
     */
    public function get_cart(): CartResponse
    {
        return $this->perform_cart_request('cart', array('method' => 'GET', 'cookies' => $_COOKIE));
    }
    /**
     * Updates the customer address to the specified data and returns the cart.
     *
     * @see https://developer.woocommerce.com/docs/apis/store-api/resources-endpoints/cart#update-customer
     * @param string               $cart_token The cart token from the get_cart response.
     * @param array<string, mixed> $fields The address fields to set and their new values.
     * @throws Exception When request fails.
     */
    public function update_customer(string $cart_token, array $fields): CartResponse
    {
        return $this->perform_cart_request('cart/update-customer', array('method' => 'POST', 'headers' => array('cart-token' => $cart_token, 'Content-Type' => 'application/json'), 'body' => wp_json_encode($fields, \JSON_FORCE_OBJECT)));
    }
    /**
     * Sets the shipping rate and returns the cart.
     *
     * @param string $cart_token The cart token from the get_cart response.
     * @param int    $package_id The package number, normally should be 0.
     * @param string $rate_id The rate id, like "flat_rate:1".
     * @throws Exception When request fails.
     */
    public function select_shipping_rate(string $cart_token, int $package_id, string $rate_id): CartResponse
    {
        return $this->perform_cart_request('cart/select-shipping-rate', array('method' => 'POST', 'headers' => array('cart-token' => $cart_token, 'Content-Type' => 'application/json'), 'body' => wp_json_encode(array('package_id' => $package_id, 'rate_id' => $rate_id), \JSON_FORCE_OBJECT)));
    }
    protected function perform_cart_request(string $path, array $args): CartResponse
    {
        $response = $this->request($this->cart_endpoint_url($path), $args);
        if (is_wp_error($response)) {
            $error = new Exception("{$path} request failed: " . $response->get_error_message());
            $this->logger->warning($error->getMessage(), array('args' => $args, 'response' => $response));
            throw $error;
        }
        $json = json_decode($response['body'], \true);
        $error_code = $json['code'] ?? null;
        $error_message = $json['message'] ?? null;
        if ($error_code) {
            $error = new Exception("{$path} request return error: {$error_code} - {$error_message}");
            $this->logger->warning($error->getMessage(), array('args' => $args, 'response' => $response));
            throw $error;
        }
        $cart = $this->cart_factory->from_response($json);
        $cart_token = $response['headers']['cart-token'] ?? '';
        return new CartResponse($cart, $cart_token);
    }
    protected function cart_endpoint_url(string $path): string
    {
        return $this->base_api_url() . $path;
    }
    protected function base_api_url(): string
    {
        return rest_url('/wc/store/v1/');
    }
}
