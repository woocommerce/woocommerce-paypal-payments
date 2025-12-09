<?php
/**
 * PayPal Cart Response (cart checkout confirmed).
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Response
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Response;

use WC_Order;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;

class PaidCartResponse extends CartResponse {
	/**
	 * @var WC_Order|null The WooCommerce order which was created during checkout.
	 */
	protected ?WC_Order $wc_order = null;

	public function __construct( PayPalCart $cart, string $cart_id, WC_Order $wc_order ) {
		parent::__construct( $cart, $cart_id );
		$this->wc_order = $wc_order;
		$this->status   = 'COMPLETED';
	}

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
