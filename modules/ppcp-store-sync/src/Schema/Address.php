<?php

/**
 * Defines a postal address (shipping or billing).
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
/**
 * @see AddressTest - Unit tests for this class.
 */
class Address extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?string $country_code = null;
    private ?string $address_line_1 = null;
    private ?string $address_line_2 = null;
    private ?string $admin_area_2 = null;
    private ?string $admin_area_1 = null;
    private ?string $postal_code = null;
    public static function create_empty(): self
    {
        return self::from_array(array(), new StoreValidation());
    }
    protected function parse_fields(array $input, StoreValidation $validation): void
    {
        // Reset all fields.
        $this->country_code = null;
        $this->address_line_1 = null;
        $this->address_line_2 = null;
        $this->admin_area_2 = null;
        $this->admin_area_1 = null;
        $this->postal_code = null;
        // Parse mandatory fields.
        if (isset($input['country_code']) && is_string($input['country_code'])) {
            $country_code = strtoupper(trim($input['country_code']));
            if (preg_match('/^[A-Z]{2}$/', $country_code)) {
                $this->country_code = $country_code;
            } else {
                $validation->add_invalid_data('country_code', 'Unexpected country_code', 'Please provide a valid 2-letter country code.');
            }
        } else {
            $validation->add_invalid_data('country_code', 'Missing required field', 'Please provide a country code.');
        }
        // Parse optional fields.
        if (isset($input['address_line_1']) && is_string($input['address_line_1'])) {
            $address_line_1 = trim($input['address_line_1']);
            if (strlen($address_line_1) <= 300) {
                $this->address_line_1 = $address_line_1;
            } else {
                $validation->add_invalid_data('address_line_1', 'Field address_line_1 is too long', 'Please provide a valid address line 1.');
            }
        }
        if (isset($input['address_line_2']) && is_string($input['address_line_2'])) {
            $address_line_2 = trim($input['address_line_2']);
            if (strlen($address_line_2) <= 300) {
                $this->address_line_2 = $address_line_2;
            } else {
                $validation->add_invalid_data('address_line_2', 'Field address_line_2 is too long', 'Please provide a valid address line 2.');
            }
        }
        if (isset($input['admin_area_2']) && is_string($input['admin_area_2'])) {
            $admin_area_2 = trim($input['admin_area_2']);
            if (strlen($admin_area_2) <= 120) {
                $this->admin_area_2 = $admin_area_2;
            } else {
                $validation->add_invalid_data('admin_area_2', 'Field admin_area_2 is too long', 'Please provide a valid city.');
            }
        }
        if (isset($input['admin_area_1']) && is_string($input['admin_area_1'])) {
            $admin_area_1 = trim($input['admin_area_1']);
            if (strlen($admin_area_1) <= 300) {
                $this->admin_area_1 = $admin_area_1;
            } else {
                $validation->add_invalid_data('admin_area_1', 'Field admin_area_1 is too long', 'Please provide a valid region or state.');
            }
        }
        if (isset($input['postal_code']) && is_string($input['postal_code'])) {
            $postal_code = trim($input['postal_code']);
            if (strlen($postal_code) <= 60) {
                $this->postal_code = $postal_code;
            } else {
                $validation->add_invalid_data('postal_code', 'Field postal_code is too long', 'Please provide a valid postal code.');
            }
        }
    }
    public function country_code(?string $default = null): ?string
    {
        return $this->country_code ?? $default;
    }
    public function address_line_1(?string $default = null): ?string
    {
        return $this->address_line_1 ?? $default;
    }
    public function address_line_2(?string $default = null): ?string
    {
        return $this->address_line_2 ?? $default;
    }
    /**
     * The city.
     */
    public function admin_area_2(?string $default = null): ?string
    {
        return $this->admin_area_2 ?? $default;
    }
    /**
     * The region or state.
     */
    public function admin_area_1(?string $default = null): ?string
    {
        return $this->admin_area_1 ?? $default;
    }
    public function postal_code(?string $default = null): ?string
    {
        return $this->postal_code ?? $default;
    }
    /**
     * @return array{address_line_1: string, address_line_2: string, admin_area_2: string, admin_area_1: string, postal_code: string, country_code: string}
     */
    public function to_array(): array
    {
        return array('address_line_1' => (string) $this->address_line_1(''), 'address_line_2' => (string) $this->address_line_2(''), 'admin_area_2' => (string) $this->admin_area_2(''), 'admin_area_1' => (string) $this->admin_area_1(''), 'postal_code' => (string) $this->postal_code(''), 'country_code' => (string) $this->country_code(''));
    }
    public function is_empty(): bool
    {
        $data = $this->to_array();
        return count(array_filter($data, static fn($v) => $v !== '')) === 0;
    }
}
