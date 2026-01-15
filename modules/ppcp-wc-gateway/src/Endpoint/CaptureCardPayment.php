<?php

/**
 * The Capture Card Payment endpoint.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Endpoint;

use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use RuntimeException;
use stdClass;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\RequestTrait;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\RealTimeAccountUpdaterHelper;
use WP_Error;
/**
 * Class CaptureCardPayment
 */
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
    /**
     * The order endpoint.
     *
     * @var OrderEndpoint
     */
    private $order_endpoint;
    /**
     * The session handler.
     *
     * @var SessionHandler
     */
    private $session_handler;
    /**
     * Real Time Account Updater helper.
     *
     * @var RealTimeAccountUpdaterHelper
     */
    private $real_time_account_updater_helper;
    /**
     * The settings.
     *
     * @var Settings
     */
    private $settings;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    private $logger;
    /**
     * CaptureCardPayment constructor.
     *
     * @param string                       $host The host.
     * @param Bearer                       $bearer The bearer.
     * @param OrderFactory                 $order_factory The order factory.
     * @param PurchaseUnitFactory          $purchase_unit_factory The purchase unit factory.
     * @param OrderEndpoint                $order_endpoint The order endpoint.
     * @param SessionHandler               $session_handler The session handler.
     * @param RealTimeAccountUpdaterHelper $real_time_account_updater_helper Real Time Account Updater helper.
     * @param Settings                     $settings The settings.
     * @param LoggerInterface              $logger The logger.
     */
    public function __construct(string $host, Bearer $bearer, OrderFactory $order_factory, PurchaseUnitFactory $purchase_unit_factory, OrderEndpoint $order_endpoint, SessionHandler $session_handler, RealTimeAccountUpdaterHelper $real_time_account_updater_helper, Settings $settings, LoggerInterface $logger)
    {
        $this->host = $host;
        $this->bearer = $bearer;
        $this->order_factory = $order_factory;
        $this->purchase_unit_factory = $purchase_unit_factory;
        $this->order_endpoint = $order_endpoint;
        $this->session_handler = $session_handler;
        $this->real_time_account_updater_helper = $real_time_account_updater_helper;
        $this->settings = $settings;
        $this->logger = $logger;
    }
    /**
     * Creates PayPal order from the given card vault id.
     *
     * @param string   $vault_id Vault id.
     * @param string   $custom_id Custom id.
     * @param string   $invoice_id Invoice id.
     * @param WC_Order $wc_order The WC order.
     * @return stdClass
     * @throws RuntimeException When request fails.
     */
    public function create_order(string $vault_id, string $custom_id, string $invoice_id, WC_Order $wc_order): stdClass
    {
        $intent = $this->settings->has('intent') && strtoupper((string) $this->settings->get('intent')) === 'AUTHORIZE' ? 'AUTHORIZE' : 'CAPTURE';
        $items = array($this->purchase_unit_factory->from_wc_cart());
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
        if (!isset($decoded_response->invoice_id)) {
            $decoded_response->invoice_id = $invoice_id;
        }
        return $decoded_response;
    }
}
