<?php

/**
 * Defines a single cart item in the PayPalCart.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Enums\Priority;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
/**
 * @see CartItemTest - Unit tests for this class.
 */
class CartItem extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?string $id = null;
    private ?string $variant_id = null;
    private ?string $parent_id = null;
    private int $quantity = 0;
    private ?string $name = null;
    private ?string $description = null;
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $price = null;
    private ?array $selected_attributes = null;
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\GiftOptions $gift_options = null;
    protected function parse_fields(array $input, StoreValidation $validation): void
    {
        // Reset all fields.
        $this->id = null;
        $this->variant_id = null;
        $this->parent_id = null;
        $this->quantity = 0;
        $this->name = null;
        $this->description = null;
        $this->price = null;
        $this->selected_attributes = null;
        $this->gift_options = null;
        // Parse mandatory fields.
        if (isset($input['quantity']) && is_numeric($input['quantity'])) {
            $quantity = (int) $input['quantity'];
            if ($quantity < 1 || $quantity > 999) {
                $validation->add_invalid_data('quantity', 'Quantity is invalid', 'Item quantity must be between 1 and 999')->add_resolution(ResolutionOption::create_modify_cart()->label('Set a valid quantity (1–999)')->priority(Priority::HIGH)->set_meta('min_quantity', 1)->set_meta('max_quantity', 999));
            } else {
                $this->quantity = $quantity;
            }
        } else {
            $validation->add_missing_field('quantity', 'The quantity field is required.');
        }
        // Parse optional fields.
        if (isset($input['item_id']) && is_string($input['item_id'])) {
            $id = trim($input['item_id']);
            if (strlen($id) > 127) {
                $validation->add_invalid_data('item_id', 'Item id too long', 'The item ID can be at most 127 characters long');
            } else {
                $this->id = $id;
            }
        }
        if (isset($input['variant_id']) && is_string($input['variant_id'])) {
            $variant_id = trim($input['variant_id']);
            if (strlen($variant_id) > 127) {
                $validation->add_invalid_data('variant_id', 'Variant id too long', 'The variant ID can be at most 127 characters long');
            } else {
                $this->variant_id = $variant_id;
            }
        }
        if (isset($input['parent_id']) && is_string($input['parent_id'])) {
            $parent_id = trim($input['parent_id']);
            if (strlen($parent_id) > 127) {
                $validation->add_invalid_data('parent_id', 'Parent id too long', 'The parent ID can be at most 127 characters long');
            } else {
                $this->parent_id = $parent_id;
            }
        }
        if (isset($input['name']) && is_string($input['name'])) {
            $name = trim($input['name']);
            if (strlen($name) > 127) {
                $validation->add_invalid_data('name', 'Item name too long', 'The item name can be at most 127 characters long');
            } else {
                $this->name = $name;
            }
        }
        if (isset($input['description']) && is_string($input['description'])) {
            $description = trim($input['description']);
            if (strlen($description) > 255) {
                $validation->add_invalid_data('description', 'Item description too long', 'The item description can be at most 127 characters long');
            } else {
                $this->description = $description;
            }
        }
        if (isset($input['price']) && is_array($input['price'])) {
            $price = \WooCommerce\PayPalCommerce\StoreSync\Schema\Money::from_array($input['price'], $validation);
            if ($price->value() <= 0.0) {
                $validation->add_invalid_data('price', 'Item price is invalid', 'The item price is invalid');
            } else {
                $this->price = $price;
            }
        }
        if (isset($input['gift_options']) && is_array($input['gift_options'])) {
            $this->gift_options = \WooCommerce\PayPalCommerce\StoreSync\Schema\GiftOptions::from_array($input['gift_options'], $validation);
        }
        if (isset($input['selected_attributes']) && is_array($input['selected_attributes'])) {
            $attributes = $input['selected_attributes'];
            if (count($attributes) > 10) {
                $validation->add_invalid_data('selected_attributes', 'Too many attributes', 'The item can have at most 10 attributes');
            } else {
                $attributes = array_filter($attributes, static fn($attribute) => is_array($attribute) && !empty($attribute['name']));
                $this->selected_attributes = array();
                foreach ($attributes as $attribute) {
                    $this->selected_attributes[] = array('name' => $attribute['name'], 'value' => $attribute['value'] ?? '');
                }
            }
        }
    }
    /**
     * @deprecated Use `variant_id` instead
     */
    public function item_id(?string $default = null): ?string
    {
        return $this->id ?? $default;
    }
    public function variant_id(?string $default = null): ?string
    {
        return $this->variant_id ?? $default;
    }
    public function parent_id(?string $default = null): ?string
    {
        return $this->parent_id ?? $default;
    }
    public function quantity(): int
    {
        return $this->quantity;
    }
    public function name(?string $default = null): ?string
    {
        return $this->name ?? $default;
    }
    public function description(?string $default = null): ?string
    {
        return $this->description ?? $default;
    }
    public function price(?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $default = null): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money
    {
        return $this->price ?? $default;
    }
    public function selected_attributes(?array $default = null): ?array
    {
        return $this->selected_attributes ?? $default;
    }
    public function gift_options(?\WooCommerce\PayPalCommerce\StoreSync\Schema\GiftOptions $default = null): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\GiftOptions
    {
        return $this->gift_options ?? $default;
    }
    public function to_array(): array
    {
        $data = array('quantity' => $this->quantity, 'price' => $this->price ? $this->price->to_array() : null, 'item_id' => $this->id, 'variant_id' => $this->variant_id, 'parent_id' => $this->parent_id, 'name' => $this->name, 'description' => $this->description, 'selected_attributes' => $this->selected_attributes, 'gift_options' => $this->gift_options);
        return array_filter($data, static fn($v) => $v !== null);
    }
}
