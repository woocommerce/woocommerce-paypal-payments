<?php
/**
 * Defines a single cart item in the PayPalCart.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

use WooCommerce\PayPalCommerce\AgenticCommerce\Enums\Priority;
use WooCommerce\PayPalCommerce\AgenticCommerce\Schema\ResolutionOption;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\MissingField;
use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\InvalidData;

/**
 * @see CartItemTest - Unit tests for this class.
 */
class CartItem extends AgenticSchema {
	private ?string $id = null;

	private ?string $variant_id = null;

	private ?string $parent_id = null;

	private int $quantity = 0;

	private ?string $name = null;

	private ?string $description = null;

	private ?Money $price = null;

	private ?array $selected_attributes = null;

	private ?GiftOptions $gift_options = null;

	protected function parse_fields( array $input, callable $add_issue ): void {
		// Reset all fields.
		$this->id                  = null;
		$this->variant_id          = null;
		$this->parent_id           = null;
		$this->quantity            = 0;
		$this->name                = null;
		$this->description         = null;
		$this->price               = null;
		$this->selected_attributes = null;
		$this->gift_options        = null;

		// Parse mandatory fields.
		if ( isset( $input['quantity'] ) && is_numeric( $input['quantity'] ) ) {
			$quantity = (int) $input['quantity'];

			if ( $quantity < 1 || $quantity > 999 ) {
				$add_issue(
					new InvalidData(
						'Quantity is invalid',
						'Item quantity must be between 1 and 999',
						'quantity',
						'',
						array(),
						array(
							ResolutionOption::modify_cart(
								'Set a valid quantity (1–999)',
								array(
									'priority'     => Priority::HIGH,
									'min_quantity' => 1,
									'max_quantity' => 999,
								)
							),
						)
					)
				);
			} else {
				$this->quantity = $quantity;
			}
		} else {
			$add_issue( new MissingField( 'Quantity missing', 'The quantity field is required.', 'quantity' ) );
		}

		// Parse optional fields.
		if ( isset( $input['item_id'] ) && is_string( $input['item_id'] ) ) {
			$id = trim( $input['item_id'] );

			if ( strlen( $id ) > 127 ) {
				$add_issue( new InvalidData( 'Item id too long', 'The item ID can be at most 127 characters long', 'item_id' ) );
			} else {
				$this->id = $id;
			}
		}

		if ( isset( $input['variant_id'] ) && is_string( $input['variant_id'] ) ) {
			$variant_id = trim( $input['variant_id'] );

			if ( strlen( $variant_id ) > 127 ) {
				$add_issue( new InvalidData( 'Variant id too long', 'The variant ID can be at most 127 characters long', 'variant_id' ) );
			} else {
				$this->variant_id = $variant_id;
			}
		}

		if ( isset( $input['parent_id'] ) && is_string( $input['parent_id'] ) ) {
			$parent_id = trim( $input['parent_id'] );

			if ( strlen( $parent_id ) > 127 ) {
				$add_issue( new InvalidData( 'Parent id too long', 'The parent ID can be at most 127 characters long', 'parent_id' ) );
			} else {
				$this->parent_id = $parent_id;
			}
		}

		if ( isset( $input['name'] ) && is_string( $input['name'] ) ) {
			$name = trim( $input['name'] );

			if ( strlen( $name ) > 127 ) {
				$add_issue( new InvalidData( 'Item name too long', 'The item name can be at most 127 characters long', 'name' ) );
			} else {
				$this->name = $name;
			}
		}

		if ( isset( $input['description'] ) && is_string( $input['description'] ) ) {
			$description = trim( $input['description'] );

			if ( strlen( $description ) > 255 ) {
				$add_issue( new InvalidData( 'Item description too long', 'The item description can be at most 127 characters long', 'description' ) );
			} else {
				$this->description = $description;
			}
		}

		if ( isset( $input['price'] ) && is_array( $input['price'] ) ) {
			$price = Money::from_array( $input['price'], $add_issue );

			if ( $price->value() <= 0. ) {
				$add_issue( new InvalidData( 'Item price is invalid', 'The item price is invalid', 'price' ) );
			} else {
				$this->price = $price;
			}
		}

		if ( isset( $input['gift_options'] ) && is_array( $input['gift_options'] ) ) {
			$this->gift_options = GiftOptions::from_array( $input['gift_options'], $add_issue );
		}

		if ( isset( $input['selected_attributes'] ) && is_array( $input['selected_attributes'] ) ) {
			$attributes = $input['selected_attributes'];

			if ( count( $attributes ) > 10 ) {
				$add_issue( new InvalidData( 'Too many attributes', 'The item can have at most 10 attributes', 'selected_attributes' ) );
			} else {
				$attributes = array_filter(
					$attributes,
					static fn( $attribute ) => is_array( $attribute ) && ! empty( $attribute['name'] )
				);

				$this->selected_attributes = array();
				foreach ( $attributes as $attribute ) {
					$this->selected_attributes[] = array(
						'name'  => $attribute['name'],
						'value' => $attribute['value'] ?? '',
					);
				}
			}
		}
	}

	public function item_id(): ?string {
		return $this->id;
	}

	public function variant_id(): ?string {
		return $this->variant_id;
	}

	public function parent_id(): ?string {
		return $this->parent_id;
	}

	public function quantity(): int {
		return $this->quantity;
	}

	public function name(): ?string {
		return $this->name;
	}

	public function description(): ?string {
		return $this->description;
	}

	public function price(): ?Money {
		return $this->price;
	}

	public function selected_attributes(): ?array {
		return $this->selected_attributes;
	}

	public function gift_options(): ?GiftOptions {
		return $this->gift_options;
	}
}
