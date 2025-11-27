<?php
/**
 * Inventory Validator for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InsufficientQuantity;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ItemOutOfStock;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue;

/**
 * Validates inventory availability using WooCommerce stock management.
 */
class InventoryValidator {
	/**
	 * Verify inventory availability using WooCommerce stock management.
	 *
	 * @param PayPalCart $cart The cart to verify.
	 * @return ValidationIssue[] Array of validation issues if any.
	 */
	public function verify_inventory( PayPalCart $cart ): array {
		$issues = array();

		foreach ( $cart->items() as $item ) {
			// Get WooCommerce product.
			$product_id = wc_get_product_id_by_sku( $item->variant_id() );
			if ( ! $product_id ) {
				$product_id = wc_get_product_id_by_sku( $item->item_id() );
			}

			if ( ! $product_id ) {
				continue; // Skip if product not found.
			}

			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			// Check stock status.
			if ( ! $product->is_in_stock() ) {
				$issues[] = new ItemOutOfStock(
					'Product is no longer available',
					sprintf( '%s is currently out of stock.', $product->get_name() ),
				);
			}

			// Check quantity if managing stock.
			if ( $product->managing_stock() ) {
				$stock_quantity = $product->get_stock_quantity();

				if ( is_numeric( $stock_quantity ) && $stock_quantity < $item->quantity() ) {
					$issues[] = new InsufficientQuantity(
						'Insufficient inventory',
						// TODO should we actually expose the real stock qty here?
						sprintf(
							'Only %d of %s available, but %d requested.',
							$stock_quantity,
							$product->get_name(),
							$item->quantity()
						),
					);
				}
			}
		}

		return $issues;
	}
}
