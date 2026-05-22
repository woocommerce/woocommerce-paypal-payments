<?php

/**
 * Defines the customer schema.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
/**
 * @see CustomerTest - Unit tests for this class.
 */
class Customer extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?string $email_address = null;
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\CustomerName $name = null;
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\CustomerPhone $phone = null;
    protected function parse_fields(array $input, StoreValidation $validation): void
    {
        // Reset all fields.
        $this->email_address = null;
        $this->name = null;
        $this->phone = null;
        // Optional fields.
        if (isset($input['email_address']) && is_string($input['email_address'])) {
            $email_address = trim($input['email_address']);
            if (filter_var($email_address, \FILTER_VALIDATE_EMAIL)) {
                $this->email_address = $email_address;
            } else {
                $validation->add_invalid_data($this->field('email_address'), 'Invalid email', 'The customers email address is not valid');
            }
        }
        if (isset($input['name']) && is_array($input['name'])) {
            $this->name = \WooCommerce\PayPalCommerce\StoreSync\Schema\CustomerName::from_array($input['name'], $validation, $this->field('name'));
        }
        if (isset($input['phone']) && is_array($input['phone'])) {
            $this->phone = \WooCommerce\PayPalCommerce\StoreSync\Schema\CustomerPhone::from_array($input['phone'], $validation, $this->field('phone'));
        }
    }
    public function email_address(?string $default = null): ?string
    {
        return $this->email_address ?? $default;
    }
    public function name(?\WooCommerce\PayPalCommerce\StoreSync\Schema\CustomerName $default = null): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\CustomerName
    {
        return $this->name ?? $default;
    }
    public function phone(?\WooCommerce\PayPalCommerce\StoreSync\Schema\CustomerPhone $default = null): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\CustomerPhone
    {
        return $this->phone ?? $default;
    }
    public function to_array(): array
    {
        $data = array('email_address' => $this->email_address(), 'name' => $this->name ? $this->name->to_array() : null, 'phone' => $this->phone ? $this->phone->to_array() : null);
        return array_filter($data, static fn($v) => $v !== null);
    }
    public function full_name(): string
    {
        return $this->name ? $this->name->full_name() : '';
    }
}
