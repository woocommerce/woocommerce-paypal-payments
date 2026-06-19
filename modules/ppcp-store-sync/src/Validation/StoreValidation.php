<?php

/**
 * Validation issue collector for a single cart-request lifecycle.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Validation
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;
/**
 * Mutable collector that owns all ValidationIssue creation for one request.
 *
 * Schema classes and validators create issues exclusively through this class,
 * ensuring no ValidationIssue is constructed outside of a tracked context.
 */
class StoreValidation
{
    /** @var ValidationIssue[] */
    private array $issues = array();
    public function add(\WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue $issue): \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
    {
        $this->issues[] = $issue;
        return $issue;
    }
    public function add_missing_field(string $field, string $user_message): \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
    {
        return $this->add(\WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue::create_missing_field("Required: {$field}")->for_field($field)->user_message($user_message));
    }
    public function add_invalid_data(string $field, string $reason, string $user_message): \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
    {
        return $this->add(\WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue::create_invalid_data($reason)->for_field($field)->user_message($user_message));
    }
    public function add_coupon_invalid(string $message): \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
    {
        return $this->add(\WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue::create_coupon_invalid($message));
    }
    public function add_currency_mismatch(string $message): \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
    {
        return $this->add(\WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue::create_currency_mismatch($message));
    }
    public function add_insufficient_quantity(string $message): \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
    {
        return $this->add(\WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue::create_insufficient_quantity($message));
    }
    public function add_item_out_of_stock(string $message): \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
    {
        return $this->add(\WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue::create_item_out_of_stock($message));
    }
    public function add_price_mismatch(string $message): \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
    {
        return $this->add(\WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue::create_price_mismatch($message));
    }
    public function add_shipping_unavailable(string $message): \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
    {
        return $this->add(\WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue::create_shipping_unavailable($message));
    }
    public function add_invalid_address(string $message): \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
    {
        return $this->add(\WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue::create_invalid_address($message));
    }
    public function add_invalid_product(string $message): \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
    {
        return $this->add(\WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue::create_invalid_product($message));
    }
    public function add_business_rule_violation(string $message): \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
    {
        return $this->add(\WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue::create_business_rule_violation($message));
    }
    public function add_payment_error(string $message): \WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue
    {
        return $this->add(\WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue::create_payment_error($message));
    }
    /** @return ValidationIssue[] */
    public function all(): array
    {
        return $this->issues;
    }
    public function is_empty(): bool
    {
        return empty($this->issues);
    }
    public function has_issue_with_code(string $code): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->code() === $code) {
                return \true;
            }
        }
        return \false;
    }
    public function has_pricing_issue(): bool
    {
        return $this->has_issue_with_code(ErrorCode::PRICING_ERROR);
    }
    public function has_issue_for_field(string $field): bool
    {
        foreach ($this->issues as $issue) {
            $data = $issue->to_array();
            if (isset($data['field']) && $data['field'] === $field) {
                return \true;
            }
        }
        return \false;
    }
}
