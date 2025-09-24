<?php
/**
 * Defines a single cart item in the PayPalCart.
 *
 * @see     https://github.com/paypal/agent-commerce/blob/28b799b0d11b6fb62f423e203de6ea4b9f2ce122/v1/docs/SCHEMA_REFERENCE.md#cartitem
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */

declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

class CartItem extends AgenticSchema {
	private ?string $id = null;

	private ?string $variant_id = null;

	private ?string $parent_id = null;

	private int $quantity = 0;

	private ?string $name = null;

	private ?string $description = null;

	private ?Money $price = null;

	private ?array $selected_attributes = null;

	private ?array $gift_options = null;

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
		if ( isset( $input['quantity'] ) ) {
			$quantity = (int) $input['quantity'];

			if ( $quantity > 0 ) {
				$this->quantity = (int) $input['quantity'];
			} else {
				$add_issue();
			}
		}

		// Parse optional fields.
		if ( isset( $input['item_id'] ) ) {
			$this->id = trim( $input['item_id'] );
		}

		if ( isset( $input['variant_id'] ) ) {
			$this->variant_id = trim( $input['variant_id'] );
		}

		if ( isset( $input['parent_id'] ) ) {
			$this->parent_id = trim( $input['parent_id'] );
		}

		if ( isset( $input['name'] ) ) {
			$this->name = trim( $input['name'] );
		}

		if ( isset( $input['description'] ) ) {
			$this->description = trim( $input['description'] );
		}

		if ( isset( $input['price'] ) ) {
			$this->price = Money::from_array( $input['price'] );
		}

		if ( isset( $input['gift_options'] ) ) {
			// todo - parse to GiftOptions.
			$this->gift_options = $input['gift_options'];
		}

		if ( isset( $input['selected_attributes'] ) && is_array( $input['selected_attributes'] ) ) {
			$this->selected_attributes = array();

			foreach ( $input['selected_attributes'] as $attribute ) {
				// todo - parse to Attribute.
				$this->selected_attributes[] = $attribute;
			}
		}
	}

	public function id(): ?string {
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

	public function gift_options(): ?array {
		return $this->gift_options;
	}
}
