<?php

/**
 * Defines the customer phone schema.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
class CustomerPhone extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?string $country_code = null;
    private ?string $national_number = null;
    protected function parse_fields(array $input, StoreValidation $validation): void
    {
        $this->country_code = null;
        $this->national_number = null;
        $country_code = $input['country_code'] ?? null;
        $national_number = $input['national_number'] ?? null;
        if (is_string($country_code)) {
            $country_code = trim($country_code);
            if (!is_numeric($country_code) || '0' === $country_code) {
                $validation->add_invalid_data($this->field('country_code'), 'Invalid country code format', 'The customers phone country-code must be numeric');
            } elseif (strlen($country_code) > 3) {
                $validation->add_invalid_data($this->field('country_code'), 'Invalid country code length', 'The customers phone country-code must have between 1 and 3 digits');
            } else {
                $this->country_code = $country_code;
            }
        }
        if (is_string($national_number)) {
            $national_number = trim($national_number);
            if (!is_numeric($national_number)) {
                $validation->add_invalid_data($this->field('national_number'), 'Invalid national number format', 'The customers phone number must be numeric');
            } elseif (strlen($national_number) > 14) {
                $validation->add_invalid_data($this->field('national_number'), 'Invalid national number length', 'The customers phone number must have between 1 and 3 digits');
            } else {
                $this->national_number = $national_number;
            }
        }
    }
    public function country_code(?string $default = null): ?string
    {
        return $this->country_code ?? $default;
    }
    public function national_number(?string $default = null): ?string
    {
        return $this->national_number ?? $default;
    }
    /**
     * An array containing `{ country_code, national_number }`.
     */
    public function to_array(): array
    {
        return array('country_code' => $this->country_code(), 'national_number' => $this->national_number());
    }
}
