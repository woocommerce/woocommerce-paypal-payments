<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextBusinessRuleIssue;
/**
 * Context class for business-rule-related validation issues.
 *
 * All props are optional and included in to_array() only when set.
 */
class BusinessRuleErrorContext extends \WooCommerce\PayPalCommerce\StoreSync\Validation\Context\IssueContext
{
    public static function create_minimum_order_not_met(): self
    {
        return new self(ContextBusinessRuleIssue::MINIMUM_ORDER_NOT_MET);
    }
    public static function create_minimum_quantity_not_met(): self
    {
        return new self(ContextBusinessRuleIssue::MINIMUM_QUANTITY_NOT_MET);
    }
    public static function create_maximum_quantity_exceeded(): self
    {
        return new self(ContextBusinessRuleIssue::MAXIMUM_QUANTITY_EXCEEDED);
    }
    public static function create_cart_limit_exceeded(): self
    {
        return new self(ContextBusinessRuleIssue::CART_LIMIT_EXCEEDED);
    }
    public static function create_customer_account_suspended(): self
    {
        return new self(ContextBusinessRuleIssue::CUSTOMER_ACCOUNT_SUSPENDED);
    }
    public static function create_purchase_limit_exceeded(): self
    {
        return new self(ContextBusinessRuleIssue::PURCHASE_LIMIT_EXCEEDED);
    }
    public static function create_bulk_order_approval_required(): self
    {
        return new self(ContextBusinessRuleIssue::BULK_ORDER_APPROVAL_REQUIRED);
    }
    public static function create_store_temporarily_closed(): self
    {
        return new self(ContextBusinessRuleIssue::STORE_TEMPORARILY_CLOSED);
    }
    public static function create_age_restricted_product(): self
    {
        return new self(ContextBusinessRuleIssue::AGE_RESTRICTED_PRODUCT);
    }
    public static function create_loyalty_program_validation_failed(): self
    {
        return new self(ContextBusinessRuleIssue::LOYALTY_PROGRAM_VALIDATION_FAILED);
    }
    public static function create_business_hours_restriction(): self
    {
        return new self(ContextBusinessRuleIssue::BUSINESS_HOURS_RESTRICTION);
    }
    public static function create_product_archived(): self
    {
        return new self(ContextBusinessRuleIssue::PRODUCT_ARCHIVED);
    }
    private ?string $current_amount = null;
    private ?string $required_amount = null;
    private ?string $maximum_amount = null;
    private ?string $remaining_amount = null;
    private ?string $account_status = null;
    private ?string $suspension_reason = null;
    private ?string $suspension_date = null;
    private ?string $monthly_limit = null;
    private ?string $current_month_total = null;
    private ?string $reset_date = null;
    private ?int $total_quantity = null;
    private ?int $approval_threshold = null;
    private ?string $maintenance_end_time = null;
    private ?string $service_status = null;
    private ?int $retry_after = null;
    private ?string $contact_info = null;
    private ?array $restricted_items = null;
    private ?int $age_requirement = null;
    private ?array $business_hours = null;
    private ?string $shortage_amount = null;
    private ?string $exceeds_by = null;
    /**
     * Current order amount.
     */
    public function current_amount(?string $current_amount): self
    {
        $this->current_amount = $current_amount;
        return $this;
    }
    /**
     * Required minimum amount.
     */
    public function required_amount(?string $required_amount): self
    {
        $this->required_amount = $required_amount;
        return $this;
    }
    /**
     * Maximum allowed amount.
     */
    public function maximum_amount(?string $maximum_amount): self
    {
        $this->maximum_amount = $maximum_amount;
        return $this;
    }
    /**
     * Amount needed to meet minimum.
     */
    public function remaining_amount(?string $remaining_amount): self
    {
        $this->remaining_amount = $remaining_amount;
        return $this;
    }
    /**
     * Customer account status.
     */
    public function account_status(?string $account_status): self
    {
        $this->account_status = $account_status;
        return $this;
    }
    /**
     * Reason for account suspension.
     */
    public function suspension_reason(?string $suspension_reason): self
    {
        $this->suspension_reason = $suspension_reason;
        return $this;
    }
    /**
     * Date of account suspension.
     */
    public function suspension_date(?int $suspension_date): self
    {
        $this->suspension_date = $this->format_date_time($suspension_date);
        return $this;
    }
    /**
     * Monthly purchase limit.
     */
    public function monthly_limit(?string $monthly_limit): self
    {
        $this->monthly_limit = $monthly_limit;
        return $this;
    }
    /**
     * Current month purchase total.
     */
    public function current_month_total(?string $current_month_total): self
    {
        $this->current_month_total = $current_month_total;
        return $this;
    }
    /**
     * When limits reset.
     */
    public function reset_date(?int $reset_date): self
    {
        $this->reset_date = $this->format_date_time($reset_date);
        return $this;
    }
    /**
     * Total quantity in bulk order.
     */
    public function total_quantity(?int $total_quantity): self
    {
        if ($total_quantity !== null && $total_quantity >= 0) {
            $this->total_quantity = $total_quantity;
        }
        return $this;
    }
    /**
     * Quantity requiring approval.
     */
    public function approval_threshold(?int $approval_threshold): self
    {
        if ($approval_threshold !== null && $approval_threshold >= 0) {
            $this->approval_threshold = $approval_threshold;
        }
        return $this;
    }
    /**
     * When maintenance ends.
     */
    public function maintenance_end_time(?int $maintenance_end_time): self
    {
        $this->maintenance_end_time = $this->format_date_time($maintenance_end_time);
        return $this;
    }
    /**
     * Current service status.
     */
    public function service_status(?string $service_status): self
    {
        $this->service_status = $service_status;
        return $this;
    }
    /**
     * Seconds before retry recommended.
     */
    public function retry_after(?int $retry_after): self
    {
        if ($retry_after !== null && $retry_after >= 0) {
            $this->retry_after = $retry_after;
        }
        return $this;
    }
    /**
     * Support contact information.
     */
    public function contact_info(?string $contact_info): self
    {
        $this->contact_info = $contact_info;
        return $this;
    }
    /**
     * Items with restrictions.
     */
    public function restricted_items(?array $restricted_items): self
    {
        $this->restricted_items = $this->sanitize_string_array($restricted_items);
        return $this;
    }
    /**
     * Required minimum age.
     */
    public function age_requirement(?int $age_requirement): self
    {
        if ($age_requirement !== null && $age_requirement >= 1) {
            $this->age_requirement = $age_requirement;
        }
        return $this;
    }
    /**
     * Store business hours.
     */
    public function business_hours(?string $open_time, ?string $close_time, ?string $timezone): self
    {
        $hours = array_filter(array('open_time' => $open_time, 'close_time' => $close_time, 'timezone' => $timezone), static function ($v): bool {
            return $v !== null;
        });
        $this->business_hours = $hours !== array() ? $hours : null;
        return $this;
    }
    /**
     * Amount needed to meet minimum requirements.
     */
    public function shortage_amount(?string $shortage_amount): self
    {
        $this->shortage_amount = $shortage_amount;
        return $this;
    }
    /**
     * Amount by which limit is exceeded.
     */
    public function exceeds_by(?string $exceeds_by): self
    {
        $this->exceeds_by = $exceeds_by;
        return $this;
    }
    public function to_array(): array
    {
        $data = array('specific_issue' => $this->specific_issue);
        if ($this->current_amount !== null) {
            $data['current_amount'] = $this->current_amount;
        }
        if ($this->required_amount !== null) {
            $data['required_amount'] = $this->required_amount;
        }
        if ($this->maximum_amount !== null) {
            $data['maximum_amount'] = $this->maximum_amount;
        }
        if ($this->remaining_amount !== null) {
            $data['remaining_amount'] = $this->remaining_amount;
        }
        if ($this->account_status !== null) {
            $data['account_status'] = $this->account_status;
        }
        if ($this->suspension_reason !== null) {
            $data['suspension_reason'] = $this->suspension_reason;
        }
        if ($this->suspension_date !== null) {
            $data['suspension_date'] = $this->suspension_date;
        }
        if ($this->monthly_limit !== null) {
            $data['monthly_limit'] = $this->monthly_limit;
        }
        if ($this->current_month_total !== null) {
            $data['current_month_total'] = $this->current_month_total;
        }
        if ($this->reset_date !== null) {
            $data['reset_date'] = $this->reset_date;
        }
        if ($this->total_quantity !== null) {
            $data['total_quantity'] = $this->total_quantity;
        }
        if ($this->approval_threshold !== null) {
            $data['approval_threshold'] = $this->approval_threshold;
        }
        if ($this->maintenance_end_time !== null) {
            $data['maintenance_end_time'] = $this->maintenance_end_time;
        }
        if ($this->service_status !== null) {
            $data['service_status'] = $this->service_status;
        }
        if ($this->retry_after !== null) {
            $data['retry_after'] = $this->retry_after;
        }
        if ($this->contact_info !== null) {
            $data['contact_info'] = $this->contact_info;
        }
        if ($this->restricted_items !== null) {
            $data['restricted_items'] = $this->restricted_items;
        }
        if ($this->age_requirement !== null) {
            $data['age_requirement'] = $this->age_requirement;
        }
        if ($this->business_hours !== null) {
            $data['business_hours'] = $this->business_hours;
        }
        if ($this->shortage_amount !== null) {
            $data['shortage_amount'] = $this->shortage_amount;
        }
        if ($this->exceeds_by !== null) {
            $data['exceeds_by'] = $this->exceeds_by;
        }
        return $data;
    }
}
