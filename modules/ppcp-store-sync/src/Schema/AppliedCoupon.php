<?php

/**
 * Defines the applied coupon schema.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
/**
 * @see AppliedCouponTest - Unit tests for this class.
 */
class AppliedCoupon extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?string $code = null;
    private ?string $description = null;
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $discount_amount = null;
    protected function parse_fields(array $input, StoreValidation $validation): void
    {
        // Reset all fields.
        $this->code = null;
        $this->description = null;
        $this->discount_amount = null;
        // Optional fields.
        if (isset($input['code']) && is_string($input['code'])) {
            $this->code = trim($input['code']);
        }
        if (isset($input['description']) && is_string($input['description'])) {
            $this->description = trim($input['description']);
        }
        if (isset($input['discount_amount']) && is_array($input['discount_amount'])) {
            $this->discount_amount = \WooCommerce\PayPalCommerce\StoreSync\Schema\Money::from_array($input['discount_amount'], $validation);
        }
    }
    public function code(?string $default = null): ?string
    {
        return $this->code ?? $default;
    }
    public function description(?string $default = null): ?string
    {
        return $this->description ?? $default;
    }
    public function discount_amount(?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $default = null): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money
    {
        return $this->discount_amount ?? $default;
    }
}
