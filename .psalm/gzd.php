<?php

abstract class Shipment extends WC_Data {

	/**
	 * Return the shipment statuses without gzd- internal prefix.
	 *
	 * @param  string $context View or edit context.
	 * @return string
	 */
	public function get_status( $context = 'view' ) {
	}

	/**
	 * Tries to fetch the order for the current shipment.
	 *
	 * @return bool|WC_Order|null
	 */
	abstract public function get_order();

	/**
	 * Returns the shipment shipping provider.
	 *
	 * @param  string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_shipping_provider( $context = 'view' ) {
	}

	/**
	 * @return bool
	 */
	public function has_tracking() {
	}

	/**
	 * Returns the shipment tracking id.
	 *
	 * @param  string $context What the value is for. Valid values are 'view' and 'edit'.
	 * @return string
	 */
	public function get_tracking_id( $context = 'view' ) {
	}
}

/**
 * Get all shipment statuses.
 *
 * @return array
 */
function wc_gzd_get_shipment_statuses() {
}
