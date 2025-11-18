<?php
/**
 * Adapts PayPal Cart into CartData for WooCommerce order creation.
 *
 * This adapter converts PayPalCart (from AI agents) into CartData objects that can be
 * used by WooCommerceOrderCreator.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Cart
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Cart;

use WC_Data_Store;
use WC_Product;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidProduct;
use WooCommerce\PayPalCommerce\Button\Exception\ValidationException;
use WooCommerce\PayPalCommerce\Button\Session\CartData;

/**
 * Adapts PayPalCart to CartData for order creation.
 *
 * ARCHITECTURE FLOW:
 * PayPalCart → translate() → CartData → WooCommerceOrderCreator → WC_Order
 */
class PayPalCartToCartDataAdapter {

	/**
	 * The product data store.
	 *
	 * @var WC_Data_Store
	 */
	private $product_data_store;

	/**
	 * Constructor.
	 *
	 * @param WC_Data_Store $product_data_store Product data store for resolution.
	 */
	public function __construct( WC_Data_Store $product_data_store ) {
		$this->product_data_store = $product_data_store;
	}

	/**
	 * Adapt PayPalCart into a CartData instance.
	 *
	 * @param PayPalCart $paypal_cart The PayPal cart from AI agent.
	 * @return CartData The cart data ready for order creation.
	 * @throws ValidationException If validation fails with collected issues.
	 */
	public function translate( PayPalCart $paypal_cart ): CartData {
		$issues = array();

		// Validate required customer email.
		$email = $paypal_cart->customer() ? $paypal_cart->customer()->email_address() : null;
		if ( empty( $email ) ) {
			$issues[] = new InvalidProduct(
				'Missing email address.',
				'The customer email address (customer.email_address) is required to create a WooCommerce order.',
				'customer.email_address'
			);
		}

		// Build cart items array.
		$cart_items = $this->build_cart_items( $paypal_cart, $issues );

		// If we have validation issues, throw exception.
		if ( ! empty( $issues ) ) {
			$error_messages = array_map(
				function ( $issue ) {
					return $issue->to_array()['message'];
				},
				$issues
			);
			throw new ValidationException(
				$error_messages,
				'Cart validation failed'
			);
		}

		// Build coupons array (only include coupons with APPLY action that exist in WC).
		$coupons = array();
		if ( $paypal_cart->coupons() ) {
			foreach ( $paypal_cart->coupons() as $coupon ) {
				if ( $coupon->action() === 'APPLY' && $coupon->code() ) {
					$coupon_code = $coupon->code();
					// Validate coupon exists in WooCommerce.
					$wc_coupon = new \WC_Coupon( $coupon_code );
					if ( $wc_coupon->get_id() > 0 ) {
						$coupons[] = $coupon_code;
					}
				}
			}
		}

		// Determine if shipping is needed.
		$needs_shipping = (bool) $paypal_cart->shipping_address();

		// Create CartData with all necessary information.
		return new CartData(
			$cart_items,
			$coupons,
			$needs_shipping,
			0, // user_id for guest checkout.
			md5( wp_json_encode( $cart_items ) ) // cart_hash.
		);
	}

	/**
	 * Build cart items array from PayPalCart.
	 *
	 * @param PayPalCart $paypal_cart The PayPal cart.
	 * @param array      $issues Array to collect validation issues.
	 * @return array Cart items in WC format.
	 */
	private function build_cart_items( PayPalCart $paypal_cart, array &$issues ): array {
		$cart_items = array();

		foreach ( $paypal_cart->items() as $item ) {
			$variant_id = $item->variant_id();
			$item_id    = $item->item_id();
			$quantity   = $item->quantity();

			// Resolve product.
			$product = $this->resolve_product( $variant_id, $item_id, $issues );
			if ( ! $product ) {
				continue;
			}

			$product_id   = $product->get_parent_id() ?: $product->get_id();
			$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;

			// Build cart item in WC format.
			$cart_item_key = $this->generate_cart_item_key( $product_id, $variation_id );

			$line_price = (float) $product->get_price() * $quantity;

			$cart_items[ $cart_item_key ] = array(
				'key'               => $cart_item_key,
				'product_id'        => $product_id,
				'variation_id'      => $variation_id,
				'variation'         => $variation_id ? $product->get_variation_attributes() : array(),
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
	 * Resolve product from variant_id or item_id.
	 *
	 * Tries multiple resolution strategies:
	 * 1. SKU lookup for variant_id
	 * 2. SKU lookup for item_id
	 * 3. Direct ID casting for variant_id
	 * 4. Direct ID casting for item_id
	 *
	 * @param string|null $variant_id The variant/product identifier.
	 * @param string|null $item_id The item identifier.
	 * @param array       $issues Array to collect validation issues.
	 * @return WC_Product|null The resolved product or null.
	 */
	private function resolve_product( ?string $variant_id, ?string $item_id, array &$issues ): ?WC_Product {
		$product_id = null;

		// Strategy 1: Try variant_id as SKU.
		if ( $variant_id ) {
			$product_id = wc_get_product_id_by_sku( $variant_id );
		}

		// Strategy 2: Try item_id as SKU.
		if ( ! $product_id && $item_id ) {
			$product_id = wc_get_product_id_by_sku( $item_id );
		}

		// Strategy 3: Try variant_id as direct ID.
		if ( ! $product_id && $variant_id && is_numeric( $variant_id ) ) {
			$product_id = (int) $variant_id;
		}

		// Strategy 4: Try item_id as direct ID.
		if ( ! $product_id && $item_id && is_numeric( $item_id ) ) {
			$product_id = (int) $item_id;
		}

		$product = $product_id ? wc_get_product( $product_id ) : null;

		if ( ! $product ) {
			$identifier = $variant_id ?? $item_id ?? 'unknown';
			$issues[]   = new InvalidProduct(
				"Product not found: {$identifier}",
				"The product with ID '{$identifier}' does not exist.",
				'items'
			);
			return null;
		}

		// Validate product is purchasable.
		if ( ! $product->is_purchasable() ) {
			$identifier = $variant_id ?? $item_id ?? $product->get_id();
			$issues[]   = new InvalidProduct(
				"Product not purchasable: {$identifier}",
				"The product '{$identifier}' cannot be purchased.",
				'items'
			);
			return null;
		}

		return $product;
	}

	/**
	 * Generate cart item key.
	 *
	 * Creates a unique identifier for the cart item based on product ID and variation ID.
	 * Sufficient for Agentic Commerce since CartData is built for immediate order creation
	 * rather than persistent cart storage with custom data.
	 *
	 * @param int $product_id Product ID.
	 * @param int $variation_id Variation ID.
	 * @return string Cart item key.
	 */
	private function generate_cart_item_key( int $product_id, int $variation_id ): string {
		return md5( $product_id . '-' . $variation_id );
	}
}
