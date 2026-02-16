<?php

/**
 * Defines the cart totals schema.
 *
 * @package WooCommerce\PayPalCommerce\AgenticCommerce\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AgenticCommerce\Schema;

use WooCommerce\PayPalCommerce\AgenticCommerce\Validation\MissingField;
/**
 * @see CartTotalsTest - Unit tests for this class.
 */
class CartTotals extends \WooCommerce\PayPalCommerce\AgenticCommerce\Schema\AgenticSchema
{
    private ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money $total = null;
    private ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money $subtotal = null;
    private ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money $discount = null;
    private ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money $shipping = null;
    private ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money $tax = null;
    private ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money $handling = null;
    private ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money $insurance = null;
    private ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money $shipping_discount = null;
    private ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money $custom_charges = null;
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
            $add_issue(new MissingField('Total is required', 'Please provide a total amount', 'total'));
        } else {
            $money = \WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money::from_array($input['total'], $add_issue);
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
            $money = \WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money::from_array($input[$field_name], $add_issue);
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
    public function total(): ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money
    {
        return $this->total;
    }
    public function subtotal(): ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money
    {
        return $this->subtotal;
    }
    public function discount(): ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money
    {
        return $this->discount;
    }
    public function shipping(): ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money
    {
        return $this->shipping;
    }
    public function tax(): ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money
    {
        return $this->tax;
    }
    public function handling(): ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money
    {
        return $this->handling;
    }
    public function insurance(): ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money
    {
        return $this->insurance;
    }
    public function shipping_discount(): ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money
    {
        return $this->shipping_discount;
    }
    public function custom_charges(): ?\WooCommerce\PayPalCommerce\AgenticCommerce\Schema\Money
    {
        return $this->custom_charges;
    }
}
