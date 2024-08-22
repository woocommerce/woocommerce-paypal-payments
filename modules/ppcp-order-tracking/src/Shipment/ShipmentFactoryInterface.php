<?php
/**
 * The ShipmentFactory interface.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking\Shipment
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking\Shipment;

use RuntimeException;

/**
 * Can create order tracking shipment
 */
interface ShipmentFactoryInterface {

	/**
	 * Returns the new shipment instance.
	 *
	 * @param int    $wc_order_id The WC order ID.
	 * @param string $capture_id The capture ID.
	 * @param string $tracking_number The tracking number.
	 * @param string $status The shipment status.
	 * @param string $carrier The shipment carrier.
	 * @param string $carrier_name_other The shipment carrier name for "OTHER".
	 * @param int[]  $line_items The list of shipment line item IDs.
	 *
	 * @return ShipmentInterface
	 * @throws RuntimeException If problem creating.
	 */
	public function create_shipment(
		int $wc_order_id,
		string $capture_id,
		string $tracking_number,
		string $status,
		string $carrier,
		string $carrier_name_other,
		array $line_items
	): ShipmentInterface;
}
