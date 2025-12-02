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
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\ProductManager;

class InventoryValidator {
	private ProductManager $product_manager;

	public function __construct( ProductManager $product_manager ) {
		$this->product_manager = $product_manager;
	}

	public function verify_inventory( PayPalCart $cart ): array {
		$issues = array();

		foreach ( $cart->items() as $item ) {
			$product = $this->product_manager->find_product( $item );

			if ( ! $product ) {
				continue; // Skip if product not found.
			}

			// Check stock status.
			if ( ! $this->product_manager->is_in_stock( $product ) ) {
				$issues[] = new ItemOutOfStock(
					'Product is no longer available',
					sprintf( '%s is currently out of stock.', $product->get_name() ),
				);
				continue;
			}

			// Check quantity.
			if ( ! $this->product_manager->is_in_stock( $product, $item->quantity() ) ) {
				$stock_quantity = $product->get_stock_quantity() ?? 0;

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

		return $issues;
	}
}
