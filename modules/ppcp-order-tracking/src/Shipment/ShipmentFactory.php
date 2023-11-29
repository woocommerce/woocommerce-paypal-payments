<?php
/**
 * The ShipmentFactory.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking\Shipment
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking\Shipment;

/**
 * Class ShipmentFactory
 */
class ShipmentFactory implements ShipmentFactoryInterface {

	/**
	 * {@inheritDoc}
	 */
	public function create_shipment(
		int $wc_order_id,
		string $transaction_id,
		string $tracking_number,
		string $status,
		string $carrier,
		string $carrier_name_other,
		array $line_items
	): ShipmentInterface {
		return new Shipment( $wc_order_id, $transaction_id, $tracking_number, $status, $carrier, $carrier_name_other, $line_items );
	}
}
