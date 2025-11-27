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

/**
 * Validates that products in a cart exist in WooCommerce.
 */
class ProductValidator {
	/**
	 * Validate that all products in the cart exist in WooCommerce.
	 *
	 * @param PayPalCart $cart The cart to validate.
	 * @return InvalidProduct[] Array of InvalidProduct validation issues.
	 */
	public function validate_products_exist( PayPalCart $cart ): array {
		$issues = array();

		foreach ( $cart->items() as $key => $item ) {
			$product_id = null;

			// Try to find product by variant_id first, then item_id.
			$item_identifier = $item->variant_id() ?: $item->item_id();
			if ( $item_identifier ) {
				// TODO We currently only send the id. Is this needed/desired?
				$product_id = wc_get_product_id_by_sku( $item_identifier );
			}

			// If no product found by SKU, try direct ID lookup.
			if ( ! $product_id && is_numeric( $item_identifier ) ) {
				$product    = wc_get_product( (int) $item_identifier );
				$product_id = $product ? $product->get_id() : null;
			}

			// If still no product found, create InvalidProduct issue.
			if ( ! $product_id ) {
				$field = "items[{$key}]";

				$issues[] = new InvalidProduct(
					"Product '{$item_identifier}' not found in WooCommerce catalog",
					"'{$item->name()}' not found in WooCommerce catalog",
					$field
				);
			}
		}

		return $issues;
	}
}
