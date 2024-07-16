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
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;

/**
 * Class OrderMetaManager
 *
 * Manages metadata for PayPal orders, focusing on a single WooCommerce order
 * and its associated PayPal order.
 */
class OrderMetaManager implements OrderMetaManagerInterface {
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
	 * {@inheritDoc}
	 */
	public function __construct( WC_Abstract_Order $wc_order, PayPalOrder $pp_order ) {
		$this->wc_order = $wc_order;
		$this->pp_order = $pp_order;
	}

	/**
	 * {@inheritDoc}
	 */
	public function update_status() : OrderMetaManagerInterface {
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
	 * {@inheritDoc}
	 */
	public function get_status() : string {
		return (string) $this->wc_order->get_meta( self::STATUS_META_KEY );
	}

	/**
	 * {@inheritDoc}
	 */
	public function persist() : OrderMetaManagerInterface {
		$this->wc_order->save_meta_data();

		return $this;
	}
}
