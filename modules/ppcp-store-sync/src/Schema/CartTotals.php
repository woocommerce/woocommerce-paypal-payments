<?php

/**
 * Defines the cart totals schema.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
/**
 * @see CartTotalsTest - Unit tests for this class.
 */
class CartTotals extends \WooCommerce\PayPalCommerce\StoreSync\Schema\AgenticSchema
{
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $total = null;
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $subtotal = null;
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $discount = null;
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $shipping = null;
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $tax = null;
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $handling = null;
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $insurance = null;
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $shipping_discount = null;
    private ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money $custom_charges = null;
    protected function parse_fields(array $input, callable $add_issue): void
    {
        // Reset all fields.
        $this->total = null;
        $this->subtotal = null;
        $this->discount = null;
        $this->shipping = null;
        $this->tax = null;
        $this->handling = null;
        $this->insurance = null;
        $this->shipping_discount = null;
        $this->custom_charges = null;
        // Required field: total.
        if (!isset($input['total']) || !is_array($input['total'])) {
            $add_issue(ValidationIssue::create_missing_field('Total is required')->user_message('Please provide a total amount')->for_field('total'));
        } else {
            $money = \WooCommerce\PayPalCommerce\StoreSync\Schema\Money::from_array($input['total'], $add_issue);
            $issues = $money->issues();
            if (empty($issues)) {
                $this->total = $money;
            } else {
                foreach ($issues as $issue) {
                    $add_issue($issue);
                }
            }
        }
        // Optional Money fields.
        $this->parse_optional_money_field($input, 'subtotal', $add_issue);
        $this->parse_optional_money_field($input, 'discount', $add_issue);
        $this->parse_optional_money_field($input, 'shipping', $add_issue);
        $this->parse_optional_money_field($input, 'tax', $add_issue);
        $this->parse_optional_money_field($input, 'handling', $add_issue);
        $this->parse_optional_money_field($input, 'insurance', $add_issue);
        $this->parse_optional_money_field($input, 'shipping_discount', $add_issue);
        $this->parse_optional_money_field($input, 'custom_charges', $add_issue);
    }
    private function parse_optional_money_field(array $input, string $field_name, callable $add_issue): void
    {
        if (isset($input[$field_name]) && is_array($input[$field_name])) {
            $money = \WooCommerce\PayPalCommerce\StoreSync\Schema\Money::from_array($input[$field_name], $add_issue);
            $issues = $money->issues();
            if (empty($issues)) {
                $this->{$field_name} = $money;
            } else {
                foreach ($issues as $issue) {
                    $add_issue($issue);
                }
            }
        }
    }
    public function total(): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money
    {
        return $this->total;
    }
    public function subtotal(): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money
    {
        return $this->subtotal;
    }
    public function discount(): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money
    {
        return $this->discount;
    }
    public function shipping(): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money
    {
        return $this->shipping;
    }
    public function tax(): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money
    {
        return $this->tax;
    }
    public function handling(): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money
    {
        return $this->handling;
    }
    public function insurance(): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money
    {
        return $this->insurance;
    }
    public function shipping_discount(): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money
    {
        return $this->shipping_discount;
    }
    public function custom_charges(): ?\WooCommerce\PayPalCommerce\StoreSync\Schema\Money
    {
        return $this->custom_charges;
    }
}
