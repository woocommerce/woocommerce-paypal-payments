<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\ContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\NotFoundException;
use stdClass;
/**
 * A container implementation that provides access to hierarchical data in the form a container tree.
 *
 * This implementation dynamically transforms hierarchical data into a tree of containers, on-demand. The transformation
 * is performed "in-place", converting internal array and object values into containers without producing a copy or
 * internal cache, making this implementation very memory-friendly.
 *
 * **Example usage:**
 *
 * ```php
 * $data = [
 *      'config' => [
 *          'db' => [
 *              'host' => 'localhost',
 *              'port' => 3306,
 *          ],
 *      ]
 * ];
 *
 * $container = new HierarchicalContainer($data);
 * $container->get('config')->get('db')->get('host'); // "localhost"
 * ```
 *
 * @since [*next-version*]
 * @see   PathContainer For an implementation that compliments this one by allowing container trees to be accessed using
 *                      path-like keys.
 * @see   SegmentingContainer For an implementation that achieves a similar effect but for flat hierarchies.
 */
class HierarchyContainer implements ContainerInterface
{
    /**
     * @since [*next-version*]
     *
     * @var mixed[]
     */
    protected $data;
    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param mixed[] $data The hierarchical data for which to create the container tree.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    /**
     * @inheritDoc
     *
     * @since [*next-version*]
     */
    public function get($key)
    {
        if (!array_key_exists($key, $this->data)) {
            throw new NotFoundException("Key '{$key}' does not exist", 0, null);
        }
        $value = $this->data[$key];
        if ($value instanceof stdClass) {
            $value = get_object_vars($value);
        }
        if (is_array($value)) {
            $value = $this->data[$key] = new self($value);
        }
        return $value;
    }
    /**
     * @inheritDoc
     *
     * @since [*next-version*]
     */
    public function has($key)
    {
        $key = (string) $key;
        return array_key_exists($key, $this->data);
    }
}
