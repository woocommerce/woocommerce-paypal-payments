<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\VaultComponent\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentSource;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PayerFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\ShippingPreferenceFactory;
use WooCommerce\PayPalCommerce\Button\Endpoint\EndpointInterface;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
class CreateVaultOrderEndpoint implements EndpointInterface
{
    const ENDPOINT = 'ppc-vault-create-order';
    private RequestData $request_data;
    private OrderEndpoint $order_endpoint;
    private PurchaseUnitFactory $purchase_unit_factory;
    private PayerFactory $payer_factory;
    private ShippingPreferenceFactory $shipping_preference_factory;
    private LoggerInterface $logger;
    public function __construct(RequestData $request_data, OrderEndpoint $order_endpoint, PurchaseUnitFactory $purchase_unit_factory, PayerFactory $payer_factory, ShippingPreferenceFactory $shipping_preference_factory, LoggerInterface $logger)
    {
        $this->request_data = $request_data;
        $this->order_endpoint = $order_endpoint;
        $this->purchase_unit_factory = $purchase_unit_factory;
        $this->payer_factory = $payer_factory;
        $this->shipping_preference_factory = $shipping_preference_factory;
        $this->logger = $logger;
    }
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    public function handle_request(): void
    {
        try {
            $data = $this->request_data->read_request($this->nonce());
            $vault_token_id = $data['vault_token_id'] ?? '';
            if (!$vault_token_id) {
                wp_send_json_error(array('message' => __('Missing vault token ID.', 'woocommerce-paypal-payments')));
            }
            $purchase_unit = $this->purchase_unit_factory->from_wc_cart();
            $shipping_preference = $this->shipping_preference_factory->from_state($purchase_unit, 'checkout');
            $customer = WC()->customer;
            $payer = $customer ? $this->payer_factory->from_customer($customer) : null;
            $payment_source = new PaymentSource('token', (object) array('id' => $vault_token_id, 'type' => PaymentToken::TYPE_PAYMENT_METHOD_TOKEN));
            $order = $this->order_endpoint->create(array($purchase_unit), $shipping_preference, $payer, '', array(), $payment_source);
            wp_send_json_success(array('id' => $order->id()));
        } catch (\Exception $exception) {
            $this->logger->error('Vault Component: Failed to create order. ' . $exception->getMessage());
            wp_send_json_error(array('message' => $exception->getMessage()));
        }
    }
}
