<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextPricingIssue;
/**
 * Context class for pricing-related validation issues.
 *
 * All props are optional and included in to_array() only when set.
 */
class PricingErrorContext extends \WooCommerce\PayPalCommerce\StoreSync\Validation\Context\IssueContext
{
    public static function create_price_mismatch(): self
    {
        return new self(ContextPricingIssue::PRICE_MISMATCH);
    }
    public static function create_discount_expired(): self
    {
        return new self(ContextPricingIssue::DISCOUNT_EXPIRED);
    }
    public static function create_discount_usage_limit_exceeded(): self
    {
        return new self(ContextPricingIssue::DISCOUNT_USAGE_LIMIT_EXCEEDED);
    }
    public static function create_discount_customer_ineligible(): self
    {
        return new self(ContextPricingIssue::DISCOUNT_CUSTOMER_INELIGIBLE);
    }
    public static function create_discount_minimum_not_met(): self
    {
        return new self(ContextPricingIssue::DISCOUNT_MINIMUM_NOT_MET);
    }
    public static function create_tax_calculation_failed(): self
    {
        return new self(ContextPricingIssue::TAX_CALCULATION_FAILED);
    }
    public static function create_currency_not_supported(): self
    {
        return new self(ContextPricingIssue::CURRENCY_NOT_SUPPORTED);
    }
    public static function create_currency_mismatch(): self
    {
        return new self(ContextPricingIssue::CURRENCY_MISMATCH);
    }
    public static function create_promotional_conflict(): self
    {
        return new self(ContextPricingIssue::PROMOTIONAL_CONFLICT);
    }
    private const VALID_PRICE_CHANGE_REASONS = array('promotional_ended', 'promotional_started', 'market_adjustment', 'cost_increase', 'seasonal_pricing', 'component_cost_increase', 'terms_updated');
    private ?string $item_id = null;
    private ?string $original_price = null;
    private ?string $current_price = null;
    private ?string $currency_code = null;
    private ?string $price_change_reason = null;
    private ?string $price_increase = null;
    private ?string $price_decrease = null;
    private ?string $coupon_code = null;
    private ?int $usage_limit = null;
    private ?int $current_usage = null;
    private ?string $expiration_date = null;
    private ?string $minimum_order_amount = null;
    private ?array $supported_currencies = null;
    private ?array $found_currencies = null;
    private ?string $tax_service_error = null;
    private ?string $current_date = null;
    private ?string $discount_amount = null;
    private ?bool $required_currency_consistency = null;
    private ?array $mixed_items = null;
    /**
     * Item with pricing issue.
     */
    public function item_id(?string $item_id): self
    {
        $this->item_id = $item_id;
        return $this;
    }
    /**
     * Original price value.
     */
    public function original_price(?string $original_price): self
    {
        $this->original_price = $original_price;
        return $this;
    }
    /**
     * Current price value.
     */
    public function current_price(?string $current_price): self
    {
        $this->current_price = $current_price;
        return $this;
    }
    /**
     * Currency code.
     */
    public function currency_code(?string $currency_code): self
    {
        $this->currency_code = $currency_code;
        return $this;
    }
    /**
     * Reason for price change.
     */
    public function price_change_reason(?string $price_change_reason): self
    {
        if (in_array($price_change_reason, self::VALID_PRICE_CHANGE_REASONS, \true)) {
            $this->price_change_reason = $price_change_reason;
        }
        return $this;
    }
    /**
     * Amount of price increase.
     */
    public function price_increase(?string $price_increase): self
    {
        $this->price_increase = $price_increase;
        return $this;
    }
    /**
     * Amount of price decrease.
     */
    public function price_decrease(?string $price_decrease): self
    {
        $this->price_decrease = $price_decrease;
        return $this;
    }
    /**
     * Coupon code with issues.
     */
    public function coupon_code(?string $coupon_code): self
    {
        $this->coupon_code = $coupon_code;
        return $this;
    }
    /**
     * Coupon usage limit.
     */
    public function usage_limit(?int $usage_limit): self
    {
        if ($usage_limit !== null && $usage_limit >= 0) {
            $this->usage_limit = $usage_limit;
        }
        return $this;
    }
    /**
     * Current coupon usage count.
     */
    public function current_usage(?int $current_usage): self
    {
        if ($current_usage !== null && $current_usage >= 0) {
            $this->current_usage = $current_usage;
        }
        return $this;
    }
    /**
     * Discount expiration date.
     */
    public function expiration_date(?int $expiration_date): self
    {
        $this->expiration_date = $this->format_date_time($expiration_date);
        return $this;
    }
    /**
     * Minimum order for discount.
     */
    public function minimum_order_amount(?string $minimum_order_amount): self
    {
        $this->minimum_order_amount = $minimum_order_amount;
        return $this;
    }
    /**
     * List of supported currencies.
     */
    public function supported_currencies(?array $supported_currencies): self
    {
        $this->supported_currencies = $this->sanitize_string_array($supported_currencies);
        return $this;
    }
    /**
     * Multiple currencies found in cart.
     */
    public function found_currencies(?array $found_currencies): self
    {
        $this->found_currencies = $this->sanitize_string_array($found_currencies);
        return $this;
    }
    /**
     * Tax calculation service error.
     */
    public function tax_service_error(?string $tax_service_error): self
    {
        $this->tax_service_error = $tax_service_error;
        return $this;
    }
    /**
     * Current system date for comparisons.
     */
    public function current_date(?int $current_date): self
    {
        $this->current_date = $this->format_date_time($current_date);
        return $this;
    }
    /**
     * Discount amount that was applied.
     */
    public function discount_amount(?string $discount_amount): self
    {
        $this->discount_amount = $discount_amount;
        return $this;
    }
    /**
     * Whether all items must use same currency.
     */
    public function required_currency_consistency(?bool $required_currency_consistency): self
    {
        $this->required_currency_consistency = $required_currency_consistency;
        return $this;
    }
    /**
     * Adds an item with a currency mismatch to the mixed-currency items list.
     */
    public function add_mixed_item(string $item_id, string $currency): self
    {
        if ($this->mixed_items === null) {
            $this->mixed_items = array();
        }
        $this->mixed_items[] = array('item_id' => $item_id, 'currency' => $currency);
        return $this;
    }
    public function to_array(): array
    {
        $data = array('specific_issue' => $this->specific_issue);
        if ($this->item_id !== null) {
            $data['item_id'] = $this->item_id;
        }
        if ($this->original_price !== null) {
            $data['original_price'] = $this->original_price;
        }
        if ($this->current_price !== null) {
            $data['current_price'] = $this->current_price;
        }
        if ($this->currency_code !== null) {
            $data['currency_code'] = $this->currency_code;
        }
        if ($this->price_change_reason !== null) {
            $data['price_change_reason'] = $this->price_change_reason;
        }
        if ($this->price_increase !== null) {
            $data['price_increase'] = $this->price_increase;
        }
        if ($this->price_decrease !== null) {
            $data['price_decrease'] = $this->price_decrease;
        }
        if ($this->coupon_code !== null) {
            $data['coupon_code'] = $this->coupon_code;
        }
        if ($this->usage_limit !== null) {
            $data['usage_limit'] = $this->usage_limit;
        }
        if ($this->current_usage !== null) {
            $data['current_usage'] = $this->current_usage;
        }
        if ($this->expiration_date !== null) {
            $data['expiration_date'] = $this->expiration_date;
        }
        if ($this->minimum_order_amount !== null) {
            $data['minimum_order_amount'] = $this->minimum_order_amount;
        }
        if ($this->supported_currencies !== null) {
            $data['supported_currencies'] = $this->supported_currencies;
        }
        if ($this->found_currencies !== null) {
            $data['found_currencies'] = $this->found_currencies;
        }
        if ($this->tax_service_error !== null) {
            $data['tax_service_error'] = $this->tax_service_error;
        }
        if ($this->current_date !== null) {
            $data['current_date'] = $this->current_date;
        }
        if ($this->discount_amount !== null) {
            $data['discount_amount'] = $this->discount_amount;
        }
        if ($this->required_currency_consistency !== null) {
            $data['required_currency_consistency'] = $this->required_currency_consistency;
        }
        if ($this->mixed_items !== null) {
            $data['mixed_items'] = $this->mixed_items;
        }
        return $data;
    }
}
