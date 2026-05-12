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
    private ?array $name = null;
    private ?array $phone = null;
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
                $validation->add_invalid_data('email_address', 'Invalid email', 'The customers email address is not valid');
            }
        }
        if (isset($input['name']) && is_array($input['name'])) {
            $this->name = array('given_name' => null, 'surname' => null);
            $given_name = $input['name']['given_name'] ?? null;
            $surname = $input['name']['surname'] ?? null;
            if (is_string($given_name)) {
                if (strlen($given_name) > 140) {
                    $validation->add_invalid_data('name.given_name', 'Given name too long', 'The customers given name cannot be longer than 140 characters');
                } else {
                    $this->name['given_name'] = trim($given_name);
                }
            }
            if (is_string($surname)) {
                if (strlen($surname) > 140) {
                    $validation->add_invalid_data('name.surname', 'Surname too long', 'The customers surname cannot be longer than 140 characters');
                } else {
                    $this->name['surname'] = trim($surname);
                }
            }
        }
        if (isset($input['phone']) && is_array($input['phone'])) {
            $this->phone = array('country_code' => null, 'national_number' => null);
            $country_code = $input['phone']['country_code'] ?? null;
            $national_number = $input['phone']['national_number'] ?? null;
            if (is_string($country_code)) {
                $country_code = trim($country_code);
                if (!is_numeric($country_code) || '0' === $country_code) {
                    $validation->add_invalid_data('phone.country_code', 'Invalid country code format', 'The customers phone country-code must be numeric');
                } elseif (strlen($country_code) > 3) {
                    $validation->add_invalid_data('phone.country_code', 'Invalid country code length', 'The customers phone country-code must have between 1 and 3 digits');
                } else {
                    $this->phone['country_code'] = trim($country_code);
                }
            }
            if (is_string($national_number)) {
                $national_number = trim($national_number);
                if (!is_numeric($national_number)) {
                    $validation->add_invalid_data('phone.national_number', 'Invalid national number format', 'The customers phone number must be numeric');
                } elseif (strlen($national_number) > 14) {
                    $validation->add_invalid_data('phone.national_number', 'Invalid national number length', 'The customers phone number must have between 1 and 3 digits');
                } else {
                    $this->phone['national_number'] = trim($national_number);
                }
            }
        }
    }
    public function email_address(?string $default = null): ?string
    {
        return $this->email_address ?? $default;
    }
    /**
     * @return null|array Customer name as an array, no own schema.
     */
    public function name(?array $default = null): ?array
    {
        return $this->name ?? $default;
    }
    /**
     * @return null|array Phone number as an array, no own schema.
     */
    public function phone(?array $default = null): ?array
    {
        return $this->phone ?? $default;
    }
    public function to_array(): array
    {
        $data = array('email_address' => $this->email_address, 'name' => $this->name, 'phone' => $this->phone);
        return array_filter($data, static fn($v) => $v !== null);
    }
}
