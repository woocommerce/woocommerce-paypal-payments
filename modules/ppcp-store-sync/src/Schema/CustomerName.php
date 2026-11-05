<?php

/**
 * Defines the customer name schema.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
class CustomerName extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?string $given_name = null;
    private ?string $surname = null;
    protected function parse_fields(array $input, StoreValidation $validation): void
    {
        $this->given_name = null;
        $this->surname = null;
        $given_name = $input['given_name'] ?? null;
        $surname = $input['surname'] ?? null;
        if (is_string($given_name)) {
            if (strlen($given_name) > 140) {
                $validation->add_invalid_data($this->field('given_name'), 'Given name too long', 'The customers given name cannot be longer than 140 characters');
            } else {
                $this->given_name = trim($given_name);
            }
        }
        if (is_string($surname)) {
            if (strlen($surname) > 140) {
                $validation->add_invalid_data($this->field('surname'), 'Surname too long', 'The customers surname cannot be longer than 140 characters');
            } else {
                $this->surname = trim($surname);
            }
        }
    }
    public function given_name(?string $default = null): ?string
    {
        return $this->given_name ?? $default;
    }
    public function surname(?string $default = null): ?string
    {
        return $this->surname ?? $default;
    }
    /**
     * An array containing `{ given_name, surname }`.
     */
    public function to_array(): array
    {
        return array('given_name' => $this->given_name(), 'surname' => $this->surname());
    }
    public function full_name(): string
    {
        $first_name = $this->given_name('');
        $last_name = $this->surname('');
        return trim("{$first_name} {$last_name}");
    }
}
