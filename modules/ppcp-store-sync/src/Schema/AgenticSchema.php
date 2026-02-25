<?php

/**
 * Base class for the agentic commerce schema classes.
 *
 * @see     https://github.com/paypal/agent-commerce/blob/511d3b276d2bc96ebc3e9330e3d753f380323e59/v1/docs/SCHEMA_REFERENCE.md
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\ValidationIssue;
/**
 * Agentic schema classes must enforce immutability - only constructor can set properties!
 */
abstract class AgenticSchema
{
    /**
     * Holds toe raw input data passed to the `from_array` factory method.
     */
    private array $raw_data;
    /**
     * Collection of all validation issues, populated by the parse_fields method.
     *
     * @var ValidationIssue[]
     */
    private array $validation_issues = array();
    /**
     * Private constructor to enforce use of `from_array` factory method.
     *
     * @param array $raw_data The raw input data.
     */
    final private function __construct(array $raw_data)
    {
        $this->raw_data = $raw_data;
    }
    /**
     * Performs the data validation during the object construction.
     *
     * @param array    $input     The raw input data.
     * @param callable $add_issue The callback to add a new ValidationIssue.
     */
    abstract protected function parse_fields(array $input, callable $add_issue): void;
    /**
     * Returns a list of validation errors or an empty array when the object is valid.
     *
     * @return ValidationIssue[]
     */
    final public function issues(): array
    {
        return $this->validation_issues;
    }
    /**
     * Returns a key-value array that represents the object's internal state.
     *
     * Attention: This method contains UNSANITIZED data, use the schema's getter methods!
     * This method should be ONLY used by the `CartResponse` to tell the agent which payload
     * was processed to generate a given response.
     */
    final public function to_array(): array
    {
        return $this->raw_data;
    }
    /**
     * Factory method to create a new object from the key-value array.
     *
     * @param array         $data      Key-value array.
     * @param callable|null $add_issue The callback to add a new ValidationIssue; allows
     *                                 propagation of issues to the parent instance.
     * @return static New instance, or error details.
     */
    final public static function from_array(array $data, ?callable $add_issue = null): self
    {
        $instance = new static($data);
        if (null === $add_issue) {
            $add_issue = static function (ValidationIssue $issue) use ($instance): void {
                $instance->validation_issues[] = $issue;
            };
        }
        $instance->parse_fields($data, $add_issue);
        return $instance;
    }
    /**
     * Helper that creates a new instance with a specific change based on the current object.
     *
     * @param array $changes Key-value set of changes to apply.
     * @return static New instance, or error details.
     */
    final public function with(array $changes): self
    {
        $current = $this->to_array();
        $merged = array_merge($current, $changes);
        return static::from_array($merged);
    }
    /**
     * Creates a new instance with an additional validation issue.
     *
     * @param ValidationIssue ...$issues The validation issue to add.
     *
     * @return static New instance with the additional validation issue.
     */
    final public function with_validation_issues(ValidationIssue ...$issues): self
    {
        $new_instance = clone $this;
        $new_instance->validation_issues = array_merge($new_instance->validation_issues, $issues);
        return $new_instance;
    }
    final public function has_validation_issue(string $error_code): bool
    {
        $issues = $this->issues();
        foreach ($issues as $issue) {
            if ($issue->code() === $error_code) {
                return \true;
            }
        }
        return \false;
    }
}
