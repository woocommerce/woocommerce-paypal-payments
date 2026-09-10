<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Session;

use Exception;
use WC_Cart;

/**
 * Creates CartData.
 */
class CartDataFactory {
	/**
	 * Creates CartData from the WC cart.
	 *
	 * @throws Exception If WC cart is missing.
	 */
	public function from_current_cart( ?WC_Cart $cart = null ): CartData {
		if ( ! $cart ) {
			$cart = WC()->cart;
			if ( ! $cart instanceof WC_Cart ) {
				throw new Exception( 'Cart not found.' );
			}
		}

		$cart_data = new CartData(
			$cart->get_cart_for_session(),
			$cart->get_applied_coupons(),
			$cart->needs_shipping(),
			get_current_user_id(),
			$cart->get_cart_hash(),
			$this->fees( $cart )
		);

		if ( WC()->session ) {
			$cart_data->set_session_customer_id( (string) WC()->session->get_customer_id() );
		}

		return $cart_data;
	}

	/**
	 * The cart fees as plain arrays, so the snapshot survives being stored and read back.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function fees( WC_Cart $cart ): array {
		$fees = $cart->get_fees();

		/**
		 * The cart only holds fees when its totals were recalculated during this
		 * request. A cart restored from the session carries its fee totals but not
		 * the fees themselves, which is the case in the approve-order request that
		 * builds the order, so fall back to the snapshot stored on every
		 * recalculation - the same source the PayPal line items are built from.
		 */
		if ( ! $fees && WC()->session ) {
			$stored = WC()->session->get( 'ppcp_fees' );
			$fees   = is_array( $stored ) ? $stored : array();
		}

		return array_map(
			/**
			 * Param type omitted: WooCommerce documents these as stdClass, but the
			 * fee objects reach the cart through a filter any plugin can feed.
			 *
			 * @psalm-suppress MissingClosureParamType
			 */
			static function ( $fee ): array {
				return array(
					'id'        => (string) ( $fee->id ?? '' ),
					'name'      => (string) ( $fee->name ?? '' ),
					'taxable'   => (bool) ( $fee->taxable ?? false ),
					'tax_class' => (string) ( $fee->tax_class ?? '' ),
					'amount'    => (float) ( $fee->amount ?? 0 ),
					'total'     => (float) ( $fee->total ?? 0 ),
					'tax'       => (float) ( $fee->tax ?? 0 ),
					'tax_data'  => (array) ( $fee->tax_data ?? array() ),
				);
			},
			$fees
		);
	}
}
