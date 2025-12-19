<?php
/**
 * Price Validator for Agentic Commerce.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\CartValidation;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\CartHelper;
use WooCommerce\PayPalCommerce\AgenticCommerce\Helper\ProductManager;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\PayPalCart;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\CartItem;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\PriceMismatch;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\ValidationIssue;

class PriceValidator implements ValidatorInterface {
	private ProductManager $product_manager;

	public function __construct( ProductManager $product_manager ) {
		$this->product_manager = $product_manager;
	}

	public function validate( PayPalCart $cart ) {
		// Skip validation if the cart contains an inventory issue.
		if ( $cart->has_validation_issue( ErrorCode::INVENTORY_ISSUE ) ) {
			return null;
		}

		$issues = array();

		foreach ( $cart->items() as $key => $item ) {
			$issue = $this->validate_price_matches_store( $key, $item );

			if ( $issue ) {
				$issues[] = $issue;
			}
		}

		return $issues;
	}

	private function validate_price_matches_store( int $key, CartItem $item ): ?ValidationIssue {
		$field = "items[{$key}]";

		$product = $this->product_manager->find_product( $item );

		if ( ! $product ) {
			return null;
		}

		$cart_price = $item->price();

		if ( ! $cart_price ) {
			return null;
		}

		$store_price = (float) $product->get_price();

		if ( abs( $cart_price->value() - $store_price ) > 0.01 ) {
			return $this->create_price_mismatch_issue( $product, $cart_price, $store_price, $field );
		}

		return null;
	}

	private function create_price_mismatch_issue( $product, $cart_price, float $store_price, string $field ): PriceMismatch {
		$price_difference = $store_price - $cart_price->value();
		$is_increase      = $price_difference > 0;

		$context            = $this->build_mismatch_context( $cart_price, $store_price, $price_difference, $is_increase );
		$resolution_options = $this->build_resolution_options( $cart_price, $store_price, $price_difference, $is_increase );

		return new PriceMismatch(
			sprintf(
				"Price mismatch for '%s': cart price is %s but store price is %s",
				$product->get_name(),
				$cart_price->value(),
				$store_price
			),
			sprintf(
				'The price of %s has changed %s %s %s.',
				$product->get_name(),
				$is_increase ? 'from' : 'to',
				$cart_price->currency_code(),
				CartHelper::format_price( $store_price )
			),
			$field,
			$context,
			$resolution_options
		);
	}

	private function build_mismatch_context( $cart_price, float $store_price, float $price_difference, bool $is_increase ): array {
		$context = array(
			'specific_issue' => 'PRICE_MISMATCH',
			'original_price' => CartHelper::format_price( $cart_price->value() ),
			'current_price'  => CartHelper::format_price( $store_price ),
			'currency_code'  => $cart_price->currency_code(),
		);

		if ( $is_increase ) {
			$context['price_increase'] = CartHelper::format_price( $price_difference );
		} else {
			$context['price_decrease'] = CartHelper::format_price( abs( $price_difference ) );
		}

		return $context;
	}

	private function build_resolution_options( $cart_price, float $store_price, float $price_difference, bool $is_increase ): array {
		return array(
			array(
				'action'   => 'ACCEPT_NEW_PRICE',
				'label'    => sprintf( 'Continue with %s %s', $cart_price->currency_code(), CartHelper::format_price( $store_price ) ),
				'metadata' => array(
					'cost_impact' => sprintf( '%s%s', $is_increase ? '+' : '-', CartHelper::format_price( abs( $price_difference ) ) ),
					'priority'    => 'high',
				),
			),
			array(
				'action'   => 'REMOVE_ITEM',
				'label'    => 'Remove from cart',
				'metadata' => array(
					'cost_impact' => sprintf( '-%s', CartHelper::format_price( $cart_price->value() ) ),
					'priority'    => 'medium',
				),
			),
		);
	}
}
