<?php

/**
 * The Shipment integration for YITH WooCommerce Order & Shipment Tracking plugin.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking\Shipment
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderTracking\Integration;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\Compat\Integration;
use WooCommerce\PayPalCommerce\OrderTracking\Endpoint\OrderTrackingEndpoint;
use WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentFactoryInterface;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use function WooCommerce\PayPalCommerce\Api\ppcp_get_paypal_order;
/**
 * Class YithShipmentIntegration.
 */
class YithShipmentIntegration implements Integration
{
    use TransactionIdHandlingTrait;
    /**
     * The shipment factory.
     *
     * @var ShipmentFactoryInterface
     */
    protected $shipment_factory;
    /**
     * The logger.
     *
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * The order tracking endpoint.
     *
     * @var OrderTrackingEndpoint
     */
    protected $endpoint;
    /**
     * The YithShipmentIntegration constructor.
     *
     * @param ShipmentFactoryInterface $shipment_factory The shipment factory.
     * @param LoggerInterface          $logger The logger.
     * @param OrderTrackingEndpoint    $endpoint The order tracking endpoint.
     */
    public function __construct(ShipmentFactoryInterface $shipment_factory, LoggerInterface $logger, OrderTrackingEndpoint $endpoint)
    {
        $this->shipment_factory = $shipment_factory;
        $this->logger = $logger;
        $this->endpoint = $endpoint;
    }
    /**
     * {@inheritDoc}
     */
    public function integrate(): void
    {
        add_action('woocommerce_process_shop_order_meta', function (int $order_id) {
            try {
                if (!apply_filters('woocommerce_paypal_payments_sync_ywot_tracking', \true)) {
                    return;
                }
                $wc_order = wc_get_order($order_id);
                if (!is_a($wc_order, WC_Order::class)) {
                    return;
                }
                $paypal_order = ppcp_get_paypal_order($wc_order);
                $capture_id = $this->get_paypal_order_transaction_id($paypal_order);
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $tracking_number = wc_clean(wp_unslash($_POST['ywot_tracking_code'] ?? ''));
                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                $carrier = wc_clean(wp_unslash($_POST['ywot_carrier_name'] ?? ''));
                if (!$tracking_number || !is_string($tracking_number) || !$carrier || !is_string($carrier) || !$capture_id) {
                    return;
                }
                $ppcp_shipment = $this->shipment_factory->create_shipment($order_id, $capture_id, $tracking_number, 'SHIPPED', 'OTHER', $carrier, array());
                $tracking_information = $this->endpoint->get_tracking_information($order_id, $tracking_number);
                $tracking_information ? $this->endpoint->update_tracking_information($ppcp_shipment, $order_id) : $this->endpoint->add_tracking_information($ppcp_shipment, $order_id);
            } catch (Exception $exception) {
                return;
            }
        }, 500, 1);
    }
}
