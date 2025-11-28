<?php
/**
 * Product Validator for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidProduct;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\ProductManager;

class ProductValidator {
	private ProductManager $product_manager;

	public function __construct( ProductManager $product_manager ) {
		$this->product_manager = $product_manager;
	}

	public function validate_products_exist( PayPalCart $cart ): array {
		$issues = array();

		foreach ( $cart->items() as $key => $item ) {
			$variant_id = $item->variant_id();
			$item_id    = $item->item_id();

			// Resolve product using multiple strategies.
			$product = $this->product_manager->find_product( $variant_id, $item_id );

			if ( ! $product ) {
				// Product not found.
				$identifier = $variant_id ?? $item_id ?? 'unknown';
				$field      = "items[{$key}]";

				$issues[] = new InvalidProduct(
					"Product '{$identifier}' not found in WooCommerce catalog",
					"'{$item->name()}' not found in WooCommerce catalog",
					$field
				);
				continue;
			}

			// Check if product is purchasable.
			if ( ! $product->is_purchasable() ) {
				$identifier = $variant_id ?? $item_id ?? $product->get_id();
				$field      = "items[{$key}]";

				$issues[] = new InvalidProduct(
					"Product '{$identifier}' is not available for purchase",
					"'{$item->name()}' cannot be purchased at this time",
					$field
				);
			}
		}

		return $issues;
	}
}
