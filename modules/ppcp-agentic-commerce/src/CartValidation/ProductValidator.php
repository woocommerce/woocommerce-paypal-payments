<?php
/**
 * Product Validator for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\Priority;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\ProductManager;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\CartItem;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\ResolutionOption;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidProduct;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue;
use WooCommerce\PayPalCommerce\AgenticCommerce\Config\IngestionConfiguration;

class ProductValidator implements ValidatorInterface {
	private ProductManager $product_manager;
	private IngestionConfiguration $configuration;

	public function __construct( ProductManager $product_manager, IngestionConfiguration $configuration ) {
		$this->product_manager = $product_manager;
		$this->configuration   = $configuration;
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
		$variant_id = $item->variant_id();
		$item_id    = $item->item_id();
		$identifier = $variant_id ?? $item_id ?? 'unknown';
		$field      = "items[{$key}]";

		$product = $this->product_manager->find_product( $item );

		if ( ! $product ) {
			return new InvalidProduct(
				"Product '{$identifier}' not found in WooCommerce catalog",
				"'{$item->name()}' not found in WooCommerce catalog",
				$field,
				'',
				array(),
				array(
					ResolutionOption::remove_item( Priority::HIGH ),
				)
			);
		}

		if ( ! $product->is_purchasable() ) {
			return new InvalidProduct(
				"Product '{$identifier}' is not available for purchase",
				"'{$item->name()}' cannot be purchased at this time",
				$field,
				'',
				array(),
				array(
					ResolutionOption::remove_item( Priority::HIGH ),
					ResolutionOption::suggest_alternative(),
				)
			);
		}

		$filter_args = $this->configuration->get_valid_product_filters();

		$support_downloads = (bool) ( $filter_args['downloadable'] ?? false );
		$valid_status      = (array) ( $filter_args['status'] ?? array() );
		$valid_types       = (array) ( $filter_args['type'] ?? array() );

		if ( ! $support_downloads && $product->is_downloadable() ) {
			return new InvalidProduct(
				"Downloadable product '{$identifier}' is not supported",
				"'{$item->name()}' cannot be purchased at this time",
				$field
			);
		}
		if ( ! $product->is_type( $valid_types ) ) {
			return new InvalidProduct(
				"Product '{$identifier}' is not supported (unsupported product type)",
				"'{$item->name()}' cannot be purchased at this time",
				$field
			);
		}
		if ( ! in_array( $product->get_status(), $valid_status, true ) ) {
			return new InvalidProduct(
				"Product '{$identifier}' is not supported (product has an unsupported status)",
				"'{$item->name()}' cannot be purchased at this time",
				$field
			);
		}

		return null;
	}
}
