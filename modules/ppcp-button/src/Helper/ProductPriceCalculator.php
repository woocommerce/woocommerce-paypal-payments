<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

use WC_Data_Store;

/**
 * Computes product totals using WooCommerce tax functions (wc_get_price_including_tax)
 * without any cart interaction. Used as the fast path for simple and variable products
 * in the cart simulation endpoint, avoiding the overhead and side effects of creating
 * a WC_Cart instance.
 */
class ProductPriceCalculator {

	private WC_Data_Store $product_data_store;

	public function __construct( WC_Data_Store $product_data_store ) {
		$this->product_data_store = $product_data_store;
	}

	/**
	 * Sums up price * quantity for each product, resolving variable products
	 * to their selected variation. Uses wc_get_price_including_tax() which
	 * handles all tax display modes without touching the cart.
	 */
	public function calculate_total( array $products ): float {
		$total = 0.0;

		foreach ( $products as $product_data ) {
			$product  = $product_data['product'];
			$quantity = $product_data['quantity'];

			if ( $product->is_type( 'variable' ) && ! empty( $product_data['variations'] ) ) {
				$product = $this->resolve_variation( $product, $product_data['variations'] );
			}

			if ( ! $product ) {
				continue;
			}

			$total += (float) wc_get_price_including_tax(
				$product,
				array( 'qty' => $quantity )
			);
		}

		return $total;
	}

	/**
	 * Returns true when the products require a full WC_Cart simulation for accurate
	 * totals (e.g. bookings, bundles, composites, or products carrying extra plugin
	 * data that only the cart calculation engine can resolve).
	 */
	public function needs_cart_simulation( array $products ): bool {
		foreach ( $products as $product_data ) {
			$product = $product_data['product'];

			// Booking products have custom pricing logic that only the cart engine can resolve.
			if ( $product->is_type( 'booking' ) ) {
				return true;
			}

			if ( ! empty( $product_data['extra'] ) ) {
				return true;
			}
		}

		return (bool) apply_filters(
			'woocommerce_paypal_payments_simulate_cart_needs_full_simulation',
			false,
			$products
		);
	}

	private function resolve_variation( \WC_Product $product, array $variations ): ?\WC_Product {
		$attributes = array();
		foreach ( $variations as $variation ) {
			if ( ! isset( $variation['name'], $variation['value'] ) ) {
				continue;
			}
			$attributes[ $variation['name'] ] = $variation['value'];
		}

		// @phpstan-ignore method.notFound
		$variation_id = $this->product_data_store->find_matching_product_variation( $product, $attributes );

		if ( ! $variation_id ) {
			return null;
		}

		return wc_get_product( $variation_id ) ?: null;
	}
}
