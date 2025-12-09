<?php
/**
 * Cart Transformer for Agentic Commerce.
 *
 * Transforms PayPal cart structures to WooCommerce cart structures.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Helper
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Helper;

use WooCommerce\PayPalCommerce\Button\Session\CartData;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;

class CartTransformer {
	private ProductManager $product_manager;

	public function __construct( ProductManager $product_manager ) {
		$this->product_manager = $product_manager;
	}

	/**
	 * Transform PayPalCart to WooCommerce CartData.
	 *
	 * This method performs pure transformation without throwing exceptions.
	 * Products that cannot be resolved are skipped silently.
	 * Validation should be performed separately before using the CartData.
	 *
	 * @param PayPalCart $paypal_cart The PayPal cart from AI agent.
	 * @return CartData The cart data ready for order creation.
	 */
	public function paypal_cart_to_cart_data( PayPalCart $paypal_cart ): CartData {
		$cart_items = $this->build_cart_items( $paypal_cart );

		$coupons      = array();
		$cart_coupons = $paypal_cart->coupons();

		if ( $cart_coupons ) {
			foreach ( $cart_coupons as $coupon ) {
				if ( $coupon->action() !== 'APPLY' || ! $coupon->code() ) {
					continue;
				}

				$coupon_code = $coupon->code();
				// Validate coupon exists in WooCommerce.
				$wc_coupon = new \WC_Coupon( $coupon_code );
				if ( $wc_coupon->get_id() > 0 ) {
					$coupons[] = $coupon_code;
				}
			}
		}

		$needs_shipping = (bool) $paypal_cart->shipping_address();

		// Create CartData with all necessary information.
		return new CartData(
			$cart_items,
			$coupons,
			$needs_shipping,
			0, // user_id for guest checkout.
			md5( (string) wp_json_encode( $cart_items ) ) // cart_hash.
		);
	}

	/**
	 * Build cart items array from PayPalCart.
	 *
	 * @param PayPalCart $paypal_cart The PayPal cart.
	 * @return array Cart items in WC format.
	 */
	private function build_cart_items( PayPalCart $paypal_cart ): array {
		$cart_items = array();

		foreach ( $paypal_cart->items() as $item ) {
			$quantity = $item->quantity();

			// Resolve product - skip if not found.
			$product = $this->product_manager->find_product( $item );
			if ( ! $product ) {
				continue;
			}

			$product_id   = $product->get_parent_id() ?: $product->get_id();
			$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;

			$cart_item_key = $this->generate_cart_item_key( $product_id, $variation_id );

			$line_price           = (float) $product->get_price() * $quantity;
			$variation_attributes = array();

			if ( $variation_id && is_callable( array( $product, 'get_variation_attributes' ) ) ) {
				$variation_attributes = $product->get_variation_attributes();
			}

			$cart_items[ $cart_item_key ] = array(
				'key'               => $cart_item_key,
				'product_id'        => $product_id,
				'variation_id'      => $variation_id,
				'variation'         => $variation_attributes,
				'quantity'          => $quantity,
				'data'              => $product,
				'data_hash'         => wc_get_cart_item_data_hash( $product ),
				'line_tax_data'     => array(
					'subtotal' => array(),
					'total'    => array(),
				),
				'line_subtotal'     => $line_price,
				'line_subtotal_tax' => 0,
				'line_total'        => $line_price,
				'line_tax'          => 0,
			);
		}

		return $cart_items;
	}

	/**
	 * Generate cart item key.
	 *
	 * Creates a unique identifier for the cart item based on product ID and variation ID.
	 * Sufficient for Agentic Commerce since CartData is built for immediate order creation
	 * rather than persistent cart storage with custom data.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID.
	 * @return string Cart item key.
	 */
	private function generate_cart_item_key( int $product_id, int $variation_id ): string {
		return md5( (string) $product_id . '-' . (string) $variation_id );
	}
}
