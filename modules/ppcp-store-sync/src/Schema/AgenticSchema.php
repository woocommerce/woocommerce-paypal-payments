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
    private string $field_prefix = '';
    /**
     * Private constructor to enforce use of `from_array` factory method.
     */
    final private function __construct()
    {
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
     * @param string          $prefix     Dot-path prefix for all validation field names, e.g.
     *                                    'name' or 'items.2'.
     * @return static New instance.
     */
    final public static function from_array(array $data, StoreValidation $validation, string $prefix = ''): self
    {
        $instance = new static();
        $instance->field_prefix = $prefix;
        $instance->parse_fields($data, $validation);
        return $instance;
    }
    /**
     * Returns the dot-path to a field owned by this schema instance.
     *
     * Prepends the instance's own prefix so the full path is correct regardless
     * of how deeply the schema is nested.
     *
     * Examples (prefix = 'name'):
     *   $this->field( 'surname' )        // → 'name.surname'
     *   $this->field( 2, 'quantity' )    // → 'name.2.quantity'
     *   $this->field()                   // → 'name'
     *
     * @param string|int ...$segments Zero or more path segments to append.
     * @return string Full dot-separated field path.
     */
    public function field(...$segments): string
    {
        $parts = array_merge(array($this->field_prefix), $segments);
        $parts = array_values(array_filter(array_map('strval', $parts), static fn($s) => $s !== ''));
        return implode('.', $parts);
    }
}
