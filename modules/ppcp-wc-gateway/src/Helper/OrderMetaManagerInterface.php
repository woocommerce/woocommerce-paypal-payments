<?php
/**
 * Interface for order meta-data management.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\WcGateway\Helper;

use WC_Abstract_Order;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order as PayPalOrder;

/**
 * Interface OrderMetaManagerInterface
 *
 * Defines the contract for managing metadata for PayPal orders.
 * This interface provides methods for updating and retrieving order metadata,
 * focusing on a single WooCommerce order and its associated PayPal order.
 */
interface OrderMetaManagerInterface {
	public const STATUS_META_KEY = '_ppcp_paypal_status';


	/**
	 * Creates a new instance of the OrderMetaManager.
	 *
	 * @param WC_Abstract_Order $wc_order The WooCommerce order to manage metadata for.
	 * @param PayPalOrder       $pp_order The associated PayPal order.
	 */
	public function __construct( WC_Abstract_Order $wc_order, PayPalOrder $pp_order );

	/**
	 * Updates the status metadata for the WooCommerce order based on the PayPal order.
	 *
	 * @return self
	 */
	public function update_status() : self;

	/**
	 * Retrieves the PayPal order status from the WooCommerce order's metadata.
	 *
	 * @return string The PayPal order status.
	 */
	public function get_status() : string;

	/**
	 * Persists any pending metadata changes to the database.
	 *
	 * @return self
	 */
	public function persist() : self;
}
