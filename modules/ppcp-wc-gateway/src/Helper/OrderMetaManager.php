<?php
/**
 * Access order meta-data.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WC_Abstract_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order as PayPalOrder;

/**
 * Class OrderMetaManager
 *
 * Manages metadata for PayPal orders, focusing on a single WooCommerce order
 * and its associated PayPal order.
 */
class OrderMetaManager {

	public const STATUS_META_KEY = '_ppcp_paypal_status';

	/**
	 * The WooCommerce order.
	 *
	 * @var WC_Abstract_Order
	 */
	private $wc_order;

	/**
	 * The PayPal order.
	 *
	 * @var PayPalOrder
	 */
	private $pp_order;

	/**
	 * Creates a new instance of the OrderMetaManager, connecting the provided objects -
	 * a WC_Order and PayPal Order (transaction).
	 *
	 * @param WC_Abstract_Order $wc_order The WooCommerce order to manage metadata for.
	 * @param PayPalOrder       $pp_order The associated PayPal order.
	 */
	public function __construct( WC_Abstract_Order $wc_order, PayPalOrder $pp_order ) {
		$this->wc_order = $wc_order;
		$this->pp_order = $pp_order;
	}

	/**
	 * Updates the status metadata for the WooCommerce order based on the PayPal order.
	 *
	 * To guarantee that the change is saved to the database, call `::persist()`.
	 *
	 * @return self
	 */
	public function set_status() : OrderMetaManager {
		$new_status = $this->pp_order->status()->name();
		$old_status = $this->get_status();

		if ( $new_status !== $old_status ) {
			$this->wc_order->update_meta_data( self::STATUS_META_KEY, $new_status );

			/**
			 * Fires after the PayPal order status value was updated in the order meta table.
			 *
			 * @param WC_Abstract_Order $wc_order   The WooCommerce order.
			 * @param PayPalOrder       $pp_order   The PayPal order.
			 * @param string            $new_status The new status.
			 * @param string            $old_status The old status.
			 */
			do_action(
				'woocommerce_paypal_payments_order_status_changed',
				$this->wc_order,
				$this->pp_order,
				$new_status,
				$old_status
			);
		}

		return $this;
	}

	/**
	 * Retrieves the PayPal order status from the WooCommerce order's metadata.
	 *
	 * @return string The PayPal order status.
	 */
	public function get_status() : string {
		return (string) $this->wc_order->get_meta( self::STATUS_META_KEY );
	}

	/**
	 * Persists any pending metadata changes to the database.
	 *
	 * @return self
	 */
	public function persist() : OrderMetaManager {
		$this->wc_order->save_meta_data();

		return $this;
	}
}
