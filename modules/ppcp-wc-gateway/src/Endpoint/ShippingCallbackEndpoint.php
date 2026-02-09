<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Entity\ShippingOption;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AmountFactory;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Endpoint\CartEndpoint;
use WooCommerce\PayPalCommerce\WcGateway\StoreApi\Factory\ShippingRate;
use WP_REST_Response;
/**
 * Handles the shipping callback.
 */
class ShippingCallbackEndpoint
{
    private const NAMESPACE = 'paypal/v1';
    private const ROUTE = 'shipping-callback';
    private CartEndpoint $cart_endpoint;
    private AmountFactory $amount_factory;
    private LoggerInterface $logger;
    public function __construct(CartEndpoint $cart_endpoint, AmountFactory $amount_factory, LoggerInterface $logger)
    {
        $this->cart_endpoint = $cart_endpoint;
        $this->amount_factory = $amount_factory;
        $this->logger = $logger;
    }
    /**
     * Registers the endpoint.
     */
    public function register(): bool
    {
        return (bool) register_rest_route(self::NAMESPACE, self::ROUTE, array('methods' => array('POST'), 'callback' => array($this, 'handle_request'), 'permission_callback' => array($this, 'verify_request')));
    }
    public function verify_request(\WP_REST_Request $request): bool
    {
        return \true;
    }
    public function handle_request(\WP_REST_Request $request): WP_REST_Response
    {
        $cart_token = (string) $request->get_param('cart_token');
        $request_data = $request->get_params();
        $this->logger->debug('Shipping callback received: ' . $request->get_body());
        $request_id = $request_data['id'];
        $pu_id = $request_data['purchase_units'][0]['reference_id'];
        $address = $this->convert_address_to_wc($request_data['shipping_address']);
        $cart_response = $this->cart_endpoint->update_customer($cart_token, array('shipping_address' => $address));
        if (empty($cart_response->cart()->shipping_rates())) {
            $this->logger->debug('Shipping callback response: ADDRESS_ERROR (no shipping rates found).');
            return new WP_REST_Response(array('name' => 'UNPROCESSABLE_ENTITY', 'details' => array(array('issue' => 'ADDRESS_ERROR'))), 422);
        }
        if (isset($request_data['shipping_option'])) {
            $selected_shipping_method_id = $request_data['shipping_option']['id'];
            $cart_response = $this->cart_endpoint->select_shipping_rate($cart_token, 0, $selected_shipping_method_id);
        }
        $cart = $cart_response->cart();
        $amount = $this->amount_factory->from_store_api_cart($cart->totals());
        $shipping_options = array_map(function (ShippingRate $rate): ShippingOption {
            return $rate->to_paypal();
        }, $cart->shipping_rates());
        $response = array('id' => $request_id, 'purchase_units' => array(array('reference_id' => $pu_id, 'amount' => $amount->to_array(), 'shipping_options' => array_map(function (ShippingOption $shipping_option): array {
            return $shipping_option->to_array();
        }, $shipping_options))));
        $this->logger->debug('Shipping callback response: ' . (string) wp_json_encode($response));
        return new WP_REST_Response($response, 200);
    }
    /**
     * Returns the URL to the endpoint.
     */
    public function url(): string
    {
        $url = rest_url(self::NAMESPACE . '/' . self::ROUTE);
        return $url;
    }
    private function convert_address_to_wc(array $address): array
    {
        return array('country' => $address['country_code'] ?? '', 'state' => $address['admin_area_1'] ?? '', 'city' => $address['admin_area_2'] ?? '', 'postcode' => $address['postal_code'] ?? '', 'address_line_1' => '', 'address_line_2' => '');
    }
}
