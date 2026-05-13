<?php

/**
 * Base class for all business rule validations.
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Validation
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Validation;

use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorCode;
use WooCommerce\PayPalCommerce\StoreSync\Enums\ErrorType;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Resolution\ResolutionOption;
use WooCommerce\PayPalCommerce\StoreSync\Validation\Context\IssueContext;
/**
 * Implements the ValidationIssue schema.
 *
 * @see https://github.com/paypal/agent-commerce/blob/28b799b0d11b6fb62f423e203de6ea4b9f2ce122/v1/docs/SCHEMA_REFERENCE.md#validationissue
 */
class ValidationIssue
{
    private const MAX_MESSAGE_LENGTH = 255;
    private const MAX_USER_MESSAGE_LENGTH = 500;
    private const MAX_RESOLUTION_OPTIONS = 5;
    /**
     * @readonly Must only be changed via the constructor!
     */
    private string $issue_code;
    /**
     * @readonly Must only be changed via the constructor!
     */
    private string $issue_type;
    private string $message;
    private string $user_message = '';
    private string $field = '';
    private string $item_id = '';
    private array $context = array();
    private array $resolution_options = array();
    private function __construct(string $message, string $issue_code, string $issue_type)
    {
        $this->message = trim(substr($message, 0, self::MAX_MESSAGE_LENGTH));
        $this->issue_code = $issue_code;
        $this->issue_type = $issue_type;
    }
    /**
     * A generic business rule issue, intended for third-party code or cases
     * not covered by the more specific factory methods below.
     */
    public static function create_business_rule_violation(string $message): self
    {
        return new self($message, ErrorCode::BUSINESS_RULE_ERROR, ErrorType::BUSINESS_RULE);
    }
    /**
     * When to use:
     * - Coupon code is invalid or expired.
     * - Coupon not applicable to cart items.
     * - Coupon usage limit reached.
     */
    public static function create_coupon_invalid(string $message): self
    {
        return new self($message, ErrorCode::PRICING_ERROR, ErrorType::BUSINESS_RULE);
    }
    /**
     * When to use:
     * - Cart items have different currencies (mixed currency not supported).
     * - Cart currency does not match WooCommerce store currency.
     */
    public static function create_currency_mismatch(string $message): self
    {
        return new self($message, ErrorCode::PRICING_ERROR, ErrorType::BUSINESS_RULE);
    }
    /**
     * When to use:
     * - Requested quantity exceeds available stock.
     * - Stock reduced between cart creation and checkout.
     * - High-demand item with limited availability.
     */
    public static function create_insufficient_quantity(string $message): self
    {
        return new self($message, ErrorCode::INVENTORY_ISSUE, ErrorType::BUSINESS_RULE);
    }
    /**
     * When to use:
     * - Product is currently unavailable.
     * - No stock remaining.
     * - Item temporarily out of inventory.
     */
    public static function create_item_out_of_stock(string $message): self
    {
        return new self($message, ErrorCode::INVENTORY_ISSUE, ErrorType::BUSINESS_RULE);
    }
    /**
     * When to use:
     * - Product price does not match the cart value.
     * - Promotional pricing ended.
     * - Dynamic pricing adjustments occurred.
     */
    public static function create_price_mismatch(string $message): self
    {
        return new self($message, ErrorCode::PRICING_ERROR, ErrorType::BUSINESS_RULE);
    }
    /**
     * When to use:
     * - Shipping not available to a specified location.
     * - Regional restrictions apply.
     * - No shipping methods available for this address.
     */
    public static function create_shipping_unavailable(string $message): self
    {
        return new self($message, ErrorCode::SHIPPING_ERROR, ErrorType::BUSINESS_RULE);
    }
    /**
     * A generic invalid-data issue, intended for third-party code or cases
     * not covered by the more specific factory methods below.
     *
     * When to use:
     * - Provided data is incorrect, e.g., malformed email.
     * - Unexpected data format, e.g., non-numeric price.
     */
    public static function create_invalid_data(string $message): self
    {
        return new self($message, ErrorCode::DATA_ERROR, ErrorType::INVALID_DATA);
    }
    /**
     * When to use:
     * - Shipping address cannot be validated.
     * - Address is incomplete or malformed.
     * - Postal code format is invalid.
     */
    public static function create_invalid_address(string $message): self
    {
        return new self($message, ErrorCode::SHIPPING_ERROR, ErrorType::INVALID_DATA);
    }
    /**
     * When to use:
     * - Product ID doesn't exist in WooCommerce.
     * - Invalid or malformed item_id.
     */
    public static function create_invalid_product(string $message): self
    {
        return new self($message, ErrorCode::INVENTORY_ISSUE, ErrorType::INVALID_DATA);
    }
    /**
     * When to use:
     * - Required information missing, e.g., missing shipping address.
     */
    public static function create_missing_field(string $message): self
    {
        return new self($message, ErrorCode::DATA_ERROR, ErrorType::MISSING_FIELD);
    }
    /**
     * When to use:
     * - Payment was declined by the processor.
     */
    public static function create_payment_error(string $message): self
    {
        return new self($message, ErrorCode::PAYMENT_ERROR, ErrorType::BUSINESS_RULE);
    }
    /**
     * Returns the error code, which is a high-level description of the problem.
     * Possible values are defined in the `Enums/ErrorCode` class.
     */
    public function code(): string
    {
        return $this->issue_code;
    }
    /**
     * Returns the error type, which classifies the issue.
     * Possible values are defined in the `Enums/ErrorType` class.
     */
    public function type(): string
    {
        return $this->issue_type;
    }
    /**
     * Sets the field that triggered the issue.
     *
     * @param string $field Field path, e.g. "shipping_address.postal_code".
     * @return static
     */
    public function for_field(string $field): self
    {
        $this->field = $field;
        return $this;
    }
    /**
     * Sets the customer-friendly error message.
     *
     * @param string $user_message Customer-facing message.
     * @return static
     */
    public function user_message(string $user_message): self
    {
        $this->user_message = trim(substr($user_message, 0, self::MAX_USER_MESSAGE_LENGTH));
        return $this;
    }
    /**
     * Sets the cart item ID that triggered the issue.
     *
     * @param string $item_id Cart item identifier.
     * @return static
     */
    public function item_id(string $item_id): self
    {
        $this->item_id = $item_id;
        return $this;
    }
    /**
     * Adds one or more context instances to the validation issue.
     *
     * Accepts either a single IssueContext or an array of IssueContext objects.
     * Non-IssueContext values are silently ignored.
     *
     * @param IssueContext|array $context A context instance or array of instances.
     * @return static
     */
    public function add_context($context): self
    {
        if ($context instanceof IssueContext) {
            $this->context[] = $context;
            return $this;
        }
        if (is_array($context)) {
            foreach ($context as $item) {
                $this->add_context($item);
            }
        }
        return $this;
    }
    /**
     * Adds one or more resolution options to the validation issue.
     *
     * Accepts either a single ResolutionOption or an array of ResolutionOption objects.
     * Non-ResolutionOption values are silently ignored.
     * A maximum of 5 resolution options is allowed in total.
     *
     * @param ResolutionOption|array $resolution A resolution option or array of options.
     * @return static
     */
    public function add_resolution($resolution): self
    {
        if (count($this->resolution_options) >= self::MAX_RESOLUTION_OPTIONS) {
            return $this;
        }
        if ($resolution instanceof ResolutionOption) {
            $this->resolution_options[] = $resolution;
            return $this;
        }
        if (is_array($resolution)) {
            foreach ($resolution as $item) {
                $this->add_resolution($item);
            }
        }
        return $this;
    }
    public function to_array(): array
    {
        $data = array('code' => $this->code(), 'type' => $this->type(), 'message' => $this->message);
        if ($this->user_message) {
            $data['user_message'] = $this->user_message;
        }
        if ($this->field) {
            $data['field'] = $this->field;
        }
        if ($this->item_id) {
            $data['item_id'] = $this->item_id;
        }
        $context = $this->get_context();
        if ($context !== null) {
            $data['context'] = $context;
        }
        if (!empty($this->resolution_options)) {
            $data['resolution_options'] = array_map(static fn($option) => $option instanceof ResolutionOption ? $option->to_array() : $option, $this->resolution_options);
        }
        return $data;
    }
    /**
     * Returns the first context as an object for the API response.
     * Schema allows exactly one context object per issue; additional contexts are ignored.
     */
    private function get_context(): ?array
    {
        if (empty($this->context)) {
            return null;
        }
        return $this->context[0]->to_array();
    }
}
