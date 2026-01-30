<?php

/**
 * The Shipment integration for WooCommerce Shipping & Tax plugin.
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
use WooCommerce\PayPalCommerce\OrderTracking\TrackingAvailabilityTrait;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WP_HTTP_Response;
use WP_REST_Request;
use WP_REST_Server;
use function WooCommerce\PayPalCommerce\Api\ppcp_get_paypal_order;
/**
 * Class WcShippingTaxIntegration.
 */
class WcShippingTaxIntegration implements Integration
{
    use TrackingAvailabilityTrait;
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
     * The WcShippingTaxIntegration constructor.
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
        add_filter('rest_post_dispatch', function (WP_HTTP_Response $response, WP_REST_Server $server, WP_REST_Request $request): WP_HTTP_Response {
            try {
                if (!apply_filters('woocommerce_paypal_payments_sync_wc_shipping_tax', \true)) {
                    return $response;
                }
                $params = $request->get_params();
                $order_id = (int) ($params['order_id'] ?? 0);
                $label_id = (int) ($params['label_ids'] ?? 0);
                if (!$order_id || "/wc/v1/connect/label/{$order_id}/{$label_id}" !== $request->get_route()) {
                    return $response;
                }
                $data = $response->get_data() ?? array();
                $labels = $data['labels'] ?? array();
                foreach ($labels as $label) {
                    $tracking_number = $label['tracking'] ?? '';
                    if (!$tracking_number) {
                        continue;
                    }
                    $wc_order = wc_get_order($order_id);
                    if (!is_a($wc_order, WC_Order::class)) {
                        continue;
                    }
                    $paypal_order = ppcp_get_paypal_order($wc_order);
                    $capture_id = $this->get_paypal_order_transaction_id($paypal_order);
                    $carrier = $label['carrier_id'] ?? $label['service_name'] ?? '';
                    $items = array_map('intval', $label['product_ids'] ?? array());
                    if (!$carrier || !$capture_id) {
                        continue;
                    }
                    $this->sync_tracking($order_id, $capture_id, $tracking_number, $carrier, $items);
                }
            } catch (Exception $exception) {
                return $response;
            }
            return $response;
        }, 10, 3);
    }
    /**
     * Syncs (add | update) the PayPal tracking with given info.
     *
     * @param int    $wc_order_id The WC order ID.
     * @param string $capture_id The capture ID.
     * @param string $tracking_number The tracking number.
     * @param string $carrier The shipment carrier.
     * @param int[]  $items The list of line items IDs.
     * @return void
     */
    protected function sync_tracking(int $wc_order_id, string $capture_id, string $tracking_number, string $carrier, array $items)
    {
        try {
            $ppcp_shipment = $this->shipment_factory->create_shipment($wc_order_id, $capture_id, $tracking_number, 'SHIPPED', 'OTHER', $carrier, $items);
            $tracking_information = $this->endpoint->get_tracking_information($wc_order_id, $tracking_number);
            $tracking_information ? $this->endpoint->update_tracking_information($ppcp_shipment, $wc_order_id) : $this->endpoint->add_tracking_information($ppcp_shipment, $wc_order_id);
        } catch (Exception $exception) {
            $this->logger->error("Couldn't sync tracking information: " . $exception->getMessage());
        }
    }
}
