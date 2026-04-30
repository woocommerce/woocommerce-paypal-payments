<?php

/**
 * Defines a monetary amount.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
/**
 * @see MoneyTest - Unit tests for this class.
 */
class Money extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?string $currency = null;
    private ?float $value = null;
    protected function parse_fields(array $input, callable $add_issue): void
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
                $add_issue(ValidationIssue::create_invalid_data('Unexpected currency_code')->user_message('Please provide a valid 3-letter currency code.')->for_field('currency_code'));
            }
        } else {
            $add_issue(ValidationIssue::create_missing_field('Required field missing')->user_message('Please provide a currency code.')->for_field('currency_code'));
        }
        if (isset($input['value'])) {
            $value = $input['value'];
            if (is_int($value) || is_float($value)) {
                $this->value = (float) $value;
            } elseif (is_string($value) && preg_match('/^-?\d+(\.\d{2,3})?$/', $value)) {
                $this->value = (float) $value;
            } else {
                $add_issue(ValidationIssue::create_invalid_data('Unexpected money value')->user_message('Please provide a valid numerical value.')->for_field('value'));
            }
        } else {
            $add_issue(ValidationIssue::create_missing_field('Required field missing')->user_message('Please provide a value.')->for_field('value'));
        }
    }
    public function currency_code(): ?string
    {
        return $this->currency;
    }
    public function value(): ?float
    {
        return $this->value;
    }
}
