<?php

/**
 * Defines the shipping option schema.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use DateTime;
use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
/**
 * @see ShippingOptionTest - Unit tests for this class.
 */
class ShippingOption extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private string $id = '';
    private string $name = '';
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $price = null;
    private bool $is_selected = \false;
    private ?string $description = null;
    private ?string $estimated_delivery = null;
    protected function parse_fields(array $input, StoreValidation $validation): void
    {
        // Reset all fields.
        $this->id = '';
        $this->name = '';
        $this->price = null;
        $this->is_selected = \false;
        $this->description = null;
        $this->estimated_delivery = null;
        // Required field: id.
        if (!isset($input['id']) || !is_string($input['id'])) {
            $validation->add_missing_field('id', 'Please provide a shipping option ID');
        } else {
            $id = trim($input['id']);
            if (empty($id)) {
                $validation->add_missing_field('id', 'Please provide a shipping option ID');
            } else {
                $this->id = $id;
            }
        }
        // Required field: name.
        if (!isset($input['name']) || !is_string($input['name'])) {
            $validation->add_missing_field('name', 'Please provide a shipping option name');
        } else {
            $name = trim($input['name']);
            if (empty($name)) {
                $validation->add_missing_field('name', 'Please provide a shipping option name');
            } else {
                $this->name = $name;
            }
        }
        // Required field: price.
        if (!isset($input['price']) || !is_array($input['price'])) {
            $validation->add_missing_field('price', 'Please provide a shipping price');
        } else {
            $this->price = \WooCommerce\PayPalCommerce\StoreSync\Schema\Money::from_array($input['price'], $validation);
        }
        // Required field: is_selected.
        if (!isset($input['is_selected'])) {
            $validation->add_missing_field('is_selected', 'Please specify if this shipping option is selected');
        } elseif (is_bool($input['is_selected'])) {
            $this->is_selected = $input['is_selected'];
        }
        // Optional field: description.
        if (isset($input['description']) && is_string($input['description'])) {
            $this->description = trim($input['description']);
        }
        // Optional field: estimated_delivery.
        if (isset($input['estimated_delivery']) && is_string($input['estimated_delivery'])) {
            $estimated_delivery = trim($input['estimated_delivery']);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $estimated_delivery)) {
                $validation->add_invalid_data('estimated_delivery', 'Invalid delivery date format', 'The estimated delivery date must be in YYYY-MM-DD format');
            } else {
                $parsed_date = DateTime::createFromFormat('Y-m-d', $estimated_delivery);
                $real_date = $parsed_date ? $parsed_date->format('Y-m-d') : '';
                if ($real_date !== $estimated_delivery) {
                    $validation->add_invalid_data('estimated_delivery', 'Invalid date', 'The date provided does not exist (e.g., Feb 31 or month 13)');
                } else {
                    $this->estimated_delivery = $estimated_delivery;
                }
            }
        }
    }
    public function id(): string
    {
        return $this->id;
    }
    public function name(): string
    {
        return $this->name;
    }
    public function price(?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $default = null): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money
    {
        return $this->price ?? $default;
    }
    public function is_selected(): bool
    {
        return $this->is_selected;
    }
    public function description(?string $default = null): ?string
    {
        return $this->description ?? $default;
    }
    public function estimated_delivery(?string $default = null): ?string
    {
        return $this->estimated_delivery ?? $default;
    }
}
