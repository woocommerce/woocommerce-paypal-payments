<?php
/**
 * Inventory Validator for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\ProductManager;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\CartItem;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InsufficientQuantity;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ItemOutOfStock;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue;

class InventoryValidator implements ValidatorInterface {
	private ProductManager $product_manager;

	public function __construct( ProductManager $product_manager ) {
		$this->product_manager = $product_manager;
	}

	public function validate( PayPalCart $cart ): ?array {
		// Skip validation if the cart already annotates an inventory issue.
		if ( $cart->has_validation_issue( ErrorCode::INVENTORY_ISSUE ) ) {
			return null;
		}

		$issues = array();

		foreach ( $cart->items() as $key => $item ) {
			$issue = $this->validate_product( $key, $item );

			if ( $issue ) {
				$issues[] = $issue;
			}
		}

		return $issues;
	}

	private function validate_product( int $key, CartItem $item ): ?ValidationIssue {
		$field = "items[{$key}]";

		$product = $this->product_manager->find_product( $item );

		if ( ! $product ) {
			return null;
		}

		if ( ! $this->product_manager->is_in_stock( $product ) ) {
			return new ItemOutOfStock(
				'Product is no longer available',
				sprintf( '%s is currently out of stock.', $product->get_name() ),
				$field
			);
		}

		if ( ! $this->product_manager->is_in_stock( $product, $item->quantity() ) ) {
			$stock_quantity = $product->get_stock_quantity() ?? 0;

			return new InsufficientQuantity(
				'Insufficient inventory',
				sprintf(
					'Only %d of %s available, but %d requested.',
					$stock_quantity,
					$product->get_name(),
					$item->quantity()
				),
				$field
			);
		}

		return null;
	}
}
