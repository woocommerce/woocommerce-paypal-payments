<?php
/**
 * PayPal Cart Response (cart checkout confirmed).
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Response
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Response;

use WC_Order;
use WC_Cart;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;

class PaidCartResponse extends CartResponse {
	/**
	 * The WooCommerce order which was created during checkout.
	 *
	 * @var WC_Order|null
	 */
	protected ?WC_Order $wc_order = null;

	/**
	 * Constructor.
	 *
	 * @param PayPalCart $cart The PayPal cart.
	 * @param string     $cart_id The cart ID.
	 * @param WC_Order   $wc_order The WooCommerce order.
	 * @param array      $applied_coupons Applied coupons data.
	 * @param WC_Cart|null $wc_cart The WooCommerce cart.
	 */
	public function __construct(
		PayPalCart $cart,
		string $cart_id,
		WC_Order $wc_order,
		array $applied_coupons = array(),
		?WC_Cart $wc_cart = null
	) {
		parent::__construct( $cart, $applied_coupons, $cart_id, $wc_cart );
		$this->wc_order = $wc_order;
		$this->status   = 'COMPLETED';
	}

	/**
	 * Convert to array for API response.
	 *
	 * @return array The response array.
	 */
	public function to_array(): array {
		$data = parent::to_array();

		if ( $this->wc_order ) {
			$data['payment_confirmation'] = array(
				'merchant_order_number' => $this->wc_order->get_id(),
				'order_review_page'     => $this->wc_order->get_checkout_order_received_url(),
			);
		}

		return $data;
	}
}
