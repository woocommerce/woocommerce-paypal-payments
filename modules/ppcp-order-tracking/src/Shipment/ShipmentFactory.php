<?php

/**
 * The ShipmentFactory.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking\Shipment
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderTracking\Shipment;

/**
 * Class ShipmentFactory
 */
class ShipmentFactory implements \WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function create_shipment(int $wc_order_id, string $capture_id, string $tracking_number, string $status, string $carrier, string $carrier_name_other, array $line_items): \WooCommerce\PayPalCommerce\OrderTracking\Shipment\ShipmentInterface
    {
        return new \WooCommerce\PayPalCommerce\OrderTracking\Shipment\Shipment($wc_order_id, $capture_id, $tracking_number, $status, $carrier, $carrier_name_other, $line_items);
    }
}
