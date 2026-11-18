<?php

/**
 * The Shipment interface.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking\Shipment
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\OrderTracking\Shipment;

/**
 * Represents order tracking shipment
 *
 * @psalm-type LineItemId = int
 * @psalm-type LineItemMap = array{
 *     name: string,
 *     unit_amount: array{currency_code: string, value: string},
 *     quantity: int,
 *     description: string,
 *     sku: string,
 *     category: string,
 *     tax?: array{currency_code: string, value: string},
 *     tax_rate?: string
 * }
 * @psalm-type shipmentMap = array{
 *     capture_id: string,
 *     tracking_number: string,
 *     status: string,
 *     carrier: string,
 *     items: array<LineItemMap>,
 *     carrier_name_other?: string,
 * }
 */
interface ShipmentInterface
{
    /**
     * The capture ID.
     *
     * @return string
     */
    public function capture_id(): string;
    /**
     * The tracking number.
     *
     * @return string
     */
    public function tracking_number(): string;
    /**
     * The shipment status.
     *
     * @return string
     */
    public function status(): string;
    /**
     * The shipment carrier.
     *
     * @return string
     */
    public function carrier(): string;
    /**
     * The shipment carrier name for "OTHER".
     *
     * @return string
     */
    public function carrier_name_other(): string;
    /**
     * The list of shipment line items.
     *
     * @return array<int, array<string, scalar>> The map of shipment line item ID to line item map.
     * @psalm-return array<LineItemId, LineItemMap>
     */
    public function line_items(): array;
    /**
     * Renders the shipment.
     *
     * @param string[] $allowed_statuses Allowed shipping statuses.
     *
     * @return void
     */
    public function render(array $allowed_statuses): void;
    /**
     * Returns the object as array.
     *
     * @return array<string, scalar> The map of shipment object.
     * @psalm-return shipmentMap
     */
    public function to_array(): array;
}
