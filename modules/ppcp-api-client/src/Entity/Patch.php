<?php

/**
 * The Patch object.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Entity
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

/**
 * Class Patch
 */
class Patch
{
    /**
     * The operation.
     *
     * @var string
     */
    private $op;
    /**
     * The path to the change.
     *
     * @var string
     */
    private $path;
    /**
     * The new value.
     *
     * @var array
     */
    private $value;
    /**
     * Patch constructor.
     *
     * @param string $op The operation.
     * @param string $path The path.
     * @param array  $value The new value.
     */
    public function __construct(string $op, string $path, array $value)
    {
        $this->op = $op;
        $this->path = $path;
        $this->value = $value;
    }
    /**
     * Returns the operation.
     *
     * @return string
     */
    public function op(): string
    {
        return $this->op;
    }
    /**
     * Returns the path.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }
    /**
     * Returns the value.
     *
     * @return array
     */
    public function value()
    {
        return $this->value;
    }
    /**
     * Returns the object as array.
     *
     * @return array
     */
    public function to_array(): array
    {
        return array('op' => $this->op(), 'path' => $this->path(), 'value' => $this->value());
    }
    /**
     * Needed for the move operation. We currently do not
     * support the move operation.
     *
     * @return string
     */
    public function from(): string
    {
        return '';
    }
}
