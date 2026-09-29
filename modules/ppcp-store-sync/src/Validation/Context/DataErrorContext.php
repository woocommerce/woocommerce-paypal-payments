<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Validation\Context;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ContextDataIssue;
/**
 * Context class for data-related validation issues.
 *
 * All props are optional and included in to_array() only when set.
 */
class DataErrorContext extends \WooCommerce\PayPalCommerce\StoreSync\Validation\Context\IssueContext
{
    public static function create_missing_checkout_fields(): self
    {
        return new self(ContextDataIssue::MISSING_CHECKOUT_FIELDS);
    }
    public static function create_missing_payment_method(): self
    {
        return new self(ContextDataIssue::MISSING_PAYMENT_METHOD);
    }
    public static function create_missing_policy_acceptance(): self
    {
        return new self(ContextDataIssue::MISSING_POLICY_ACCEPTANCE);
    }
    public static function create_required_field_missing(): self
    {
        return new self(ContextDataIssue::REQUIRED_FIELD_MISSING);
    }
    public static function create_invalid_email_format(): self
    {
        return new self(ContextDataIssue::INVALID_EMAIL_FORMAT);
    }
    public static function create_invalid_phone_format(): self
    {
        return new self(ContextDataIssue::INVALID_PHONE_FORMAT);
    }
    public static function create_field_value_too_long(): self
    {
        return new self(ContextDataIssue::FIELD_VALUE_TOO_LONG);
    }
    public static function create_field_value_too_short(): self
    {
        return new self(ContextDataIssue::FIELD_VALUE_TOO_SHORT);
    }
    public static function create_invalid_date_format(): self
    {
        return new self(ContextDataIssue::INVALID_DATE_FORMAT);
    }
    public static function create_future_date_not_allowed(): self
    {
        return new self(ContextDataIssue::FUTURE_DATE_NOT_ALLOWED);
    }
    public static function create_invalid_customer_data(): self
    {
        return new self(ContextDataIssue::INVALID_CUSTOMER_DATA);
    }
    public static function create_item_not_found(): self
    {
        return new self(ContextDataIssue::ITEM_NOT_FOUND);
    }
    public static function create_invalid_item_data(): self
    {
        return new self(ContextDataIssue::INVALID_ITEM_DATA);
    }
    public static function create_item_attribute_mismatch(): self
    {
        return new self(ContextDataIssue::ITEM_ATTRIBUTE_MISMATCH);
    }
    private ?string $field_name = null;
    private ?string $provided_value = null;
    private ?string $expected_format = null;
    private ?int $max_length = null;
    private ?int $min_length = null;
    private ?int $current_length = null;
    private ?string $regex_pattern = null;
    private ?string $suggested_value = null;
    private ?array $allowed_values = null;
    private ?array $required_fields = null;
    private ?array $field_descriptions = null;
    /**
     * Name of the field with validation error.
     */
    public function field_name(?string $field_name): self
    {
        $this->field_name = $field_name;
        return $this;
    }
    /**
     * Value that failed validation.
     */
    public function provided_value(?string $provided_value): self
    {
        $this->provided_value = $provided_value;
        return $this;
    }
    /**
     * Expected format description.
     */
    public function expected_format(?string $expected_format): self
    {
        $this->expected_format = $expected_format;
        return $this;
    }
    /**
     * Maximum allowed length.
     */
    public function max_length(?int $max_length): self
    {
        if ($max_length !== null && $max_length >= 0) {
            $this->max_length = $max_length;
        }
        return $this;
    }
    /**
     * Minimum required length.
     */
    public function min_length(?int $min_length): self
    {
        if ($min_length !== null && $min_length >= 0) {
            $this->min_length = $min_length;
        }
        return $this;
    }
    /**
     * Current value length.
     */
    public function current_length(?int $current_length): self
    {
        if ($current_length !== null && $current_length >= 0) {
            $this->current_length = $current_length;
        }
        return $this;
    }
    /**
     * Required regex pattern.
     */
    public function regex_pattern(?string $regex_pattern): self
    {
        $this->regex_pattern = $regex_pattern;
        return $this;
    }
    /**
     * Suggested corrected value.
     */
    public function suggested_value(?string $suggested_value): self
    {
        $this->suggested_value = $suggested_value;
        return $this;
    }
    /**
     * List of allowed values for enum fields.
     */
    public function allowed_values(?array $allowed_values): self
    {
        $this->allowed_values = $this->sanitize_string_array($allowed_values);
        return $this;
    }
    /**
     * List of required field names.
     */
    public function required_fields(?array $required_fields): self
    {
        $this->required_fields = $this->sanitize_string_array($required_fields);
        return $this;
    }
    /**
     * Descriptions for required fields.
     */
    public function field_descriptions(?array $field_descriptions): self
    {
        $this->field_descriptions = $field_descriptions;
        return $this;
    }
    public function to_array(): array
    {
        $data = array('specific_issue' => $this->specific_issue);
        if ($this->field_name !== null) {
            $data['field_name'] = $this->field_name;
        }
        if ($this->provided_value !== null) {
            $data['provided_value'] = $this->provided_value;
        }
        if ($this->expected_format !== null) {
            $data['expected_format'] = $this->expected_format;
        }
        if ($this->max_length !== null) {
            $data['max_length'] = $this->max_length;
        }
        if ($this->min_length !== null) {
            $data['min_length'] = $this->min_length;
        }
        if ($this->current_length !== null) {
            $data['current_length'] = $this->current_length;
        }
        if ($this->regex_pattern !== null) {
            $data['regex_pattern'] = $this->regex_pattern;
        }
        if ($this->suggested_value !== null) {
            $data['suggested_value'] = $this->suggested_value;
        }
        if ($this->allowed_values !== null) {
            $data['allowed_values'] = $this->allowed_values;
        }
        if ($this->required_fields !== null) {
            $data['required_fields'] = $this->required_fields;
        }
        if ($this->field_descriptions !== null) {
            $data['field_descriptions'] = $this->field_descriptions;
        }
        return $data;
    }
}
