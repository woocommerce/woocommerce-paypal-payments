<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use RuntimeException;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WP_Error;
class CaptureCardPayment
{
    use RequestTrait;
    /**
     * The host.
     *
     * @var string
     */
    private $host;
    /**
     * The bearer.
     *
     * @var Bearer
     */
    private $bearer;
    /**
     * The order factory.
     *
     * @var OrderFactory
     */
    private $order_factory;
    /**
     * The purchase unit factory.
     *
     * @var PurchaseUnitFactory
     */
    private $purchase_unit_factory;
    private SettingsProvider $settings_provider;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    public function __construct(string $host, Bearer $bearer, OrderFactory $order_factory, PurchaseUnitFactory $purchase_unit_factory, SettingsProvider $settings_provider, LoggerInterface $logger)
    {
        $this->host = $host;
        $this->bearer = $bearer;
        $this->order_factory = $order_factory;
        $this->purchase_unit_factory = $purchase_unit_factory;
        $this->settings_provider = $settings_provider;
        $this->logger = $logger;
    }
    /**
     * Creates PayPal order from the given card vault id.
     *
     * @throws RuntimeException When request fails.
     */
    public function create_order(string $vault_id, string $custom_id, string $invoice_id, WC_Order $wc_order): Order
    {
        $intent = strtoupper($this->settings_provider->payment_intent()) === 'AUTHORIZE' ? 'AUTHORIZE' : 'CAPTURE';
        $items = array($this->purchase_unit_factory->from_wc_cart(null, \false, $wc_order->get_payment_method()));
        // phpcs:disable WordPress.Security.NonceVerification
        $pay_for_order = wc_clean(wp_unslash($_GET['pay_for_order'] ?? ''));
        $order_key = wc_clean(wp_unslash($_GET['key'] ?? ''));
        // phpcs:enable
        if ($pay_for_order && $order_key === $wc_order->get_order_key()) {
            $items = array($this->purchase_unit_factory->from_wc_order($wc_order));
        }
        $data = array('intent' => $intent, 'purchase_units' => array_map(static function (PurchaseUnit $item): array {
            return $item->to_array();
        }, $items), 'payment_source' => array('card' => array('vault_id' => $vault_id, 'stored_credential' => array('payment_initiator' => 'CUSTOMER', 'payment_type' => 'UNSCHEDULED', 'usage' => 'SUBSEQUENT'))), 'custom_id' => $custom_id, 'invoice_id' => $invoice_id);
        $bearer = $this->bearer->bearer();
        $url = trailingslashit($this->host) . 'v2/checkout/orders';
        $args = array('method' => 'POST', 'headers' => array('Authorization' => 'Bearer ' . $bearer->token(), 'Content-Type' => 'application/json', 'PayPal-Request-Id' => uniqid('ppcp-', \true)), 'body' => wp_json_encode($data));
        $response = $this->request($url, $args);
        if ($response instanceof WP_Error) {
            throw new RuntimeException($response->get_error_message());
        }
        $decoded_response = json_decode($response['body']);
        return $this->order_factory->from_paypal_response($decoded_response);
    }
}
