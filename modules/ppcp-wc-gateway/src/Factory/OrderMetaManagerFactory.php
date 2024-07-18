<?php
/**
 * The OrderMetaManager factory.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Factory
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Factory;

use WooCommerce\PayPalCommerce\WcGateway\Helper\OrderMetaManager;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WC_Order;

/**
 * Class OrderMetaManagerFactory
 */
class OrderMetaManagerFactory {

	/**
	 * Returns a new OrderMetaManager instance based off a WooCommerce order and an API Order
	 * object.
	 *
	 * @param WC_Order $wc_order The WooCommerce order.
	 * @param Order    $order    The order object.
	 *
	 * @return OrderMetaManager
	 */
	public function from_api_order( WC_Order $wc_order, Order $order ) : OrderMetaManager {
		return new OrderMetaManager( $wc_order, $order );
	}
}
