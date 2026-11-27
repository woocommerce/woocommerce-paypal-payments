<?php

/**
 * Defines a monetary amount.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
/**
 * @see MoneyTest - Unit tests for this class.
 */
class Money extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?string $currency = null;
    private ?float $value = null;
    /**
     * Convenience factory — accepts a param list instead of an array.
     *
     * Delegates to from_array() so parse_fields() runs identically to the parsed path.
     * Validation issues are discarded; the caller is responsible for passing valid inputs.
     *
     * @param int|float|string $value         The monetary value.
     * @param null|string      $currency_code ISO 4217 currency code.
     * @return self
     */
    public static function create($value, ?string $currency_code = null): self
    {
        return self::from_array(array('currency_code' => $currency_code, 'value' => $value), new StoreValidation());
    }
    protected function parse_fields(array $input, StoreValidation $validation): void
    {
        // Reset all fields.
        $this->currency = null;
        $this->value = null;
        // Parse mandatory fields.
        if (isset($input['currency_code']) && is_string($input['currency_code'])) {
            $currency = strtoupper(trim($input['currency_code']));
            if (preg_match('/^[A-Z]{3}$/', $currency)) {
                $this->currency = $currency;
            } else {
                $validation->add_invalid_data('currency_code', 'Unexpected currency_code', 'Please provide a valid 3-letter currency code.');
            }
        } else {
            $validation->add_missing_field('currency_code', 'Please provide a currency code.');
        }
        if (isset($input['value'])) {
            $value = $input['value'];
            if (is_int($value) || is_float($value)) {
                $this->value = (float) $value;
            } elseif (is_string($value) && preg_match('/^-?\d+(\.\d{2,3})?$/', $value)) {
                $this->value = (float) $value;
            } else {
                $validation->add_invalid_data('value', 'Unexpected money value', 'Please provide a valid numerical value.');
            }
        } else {
            $validation->add_missing_field('value', 'Please provide a value.');
        }
    }
    public function currency_code(?string $default = null): ?string
    {
        return $this->currency ?? $default;
    }
    public function value(?float $default = null): ?float
    {
        return $this->value ?? $default;
    }
    /**
     * Formats the parsed value as a 2-decimal string.
     *
     * @return string e.g. "10.00"
     */
    public function to_decimal(): string
    {
        return number_format((float) $this->value(0.0), 2, '.', '');
    }
    /**
     * Returns the PayPal API money structure.
     *
     * @return array{currency_code: string, value: string}
     */
    public function to_array(): array
    {
        return array('currency_code' => (string) $this->currency_code(''), 'value' => $this->to_decimal());
    }
    /**
     * Returns a stable, locale-independent price string for agent-facing messages.
     *
     * @return string e.g. "10.00 USD"
     */
    public function to_price(): string
    {
        return trim($this->to_decimal() . ' ' . $this->currency_code(''));
    }
}
