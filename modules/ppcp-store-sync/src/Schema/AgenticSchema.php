<?php

/**
 * Base class for the agentic commerce schema classes.
 *
 * @see     https://github.com/paypal/agent-commerce/blob/511d3b276d2bc96ebc3e9330e3d753f380323e59/v1/docs/SCHEMA_REFERENCE.md
 * @package WooCommerce\PayPalCommerce\StoreSync\Schema
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Schema;

use WooCommerce\PayPalCommerce\StoreSync\Validation\StoreValidation;
/**
 * Agentic schema classes must enforce immutability - only constructor can set properties!
 */
abstract class AgenticSchema
{
    /**
     * Holds the raw input data passed to the `from_array` factory method.
     * Used by subclasses that need to re-expose the original payload (e.g. for session storage).
     */
    private array $raw_data;
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
     * @param array           $input      The raw input data.
     * @param StoreValidation $validation Collector that receives all issues found during parsing.
     */
    abstract protected function parse_fields(array $input, StoreValidation $validation): void;
    /**
     * Factory method to create a new object from the key-value array.
     *
     * @param array           $data       Key-value array.
     * @param StoreValidation $validation Collector that receives all parse issues.
     * @return static New instance.
     */
    final public static function from_array(array $data, StoreValidation $validation): self
    {
        $instance = new static($data);
        $instance->parse_fields($data, $validation);
        return $instance;
    }
    /**
     * Exposes the raw input array for subclasses that need it (e.g. for session persistence).
     * Not for use in API responses — use getter-based serialization instead.
     */
    final protected function raw_data(): array
    {
        return $this->raw_data;
    }
}
