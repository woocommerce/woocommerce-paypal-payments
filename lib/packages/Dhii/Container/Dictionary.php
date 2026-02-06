<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use ArrayIterator;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\WritableContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\WritableMapInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Util\StringTranslatingTrait;
use IteratorAggregate;
use RangeException;
use Traversable;
/**
 * A simple mutable dictionary, i.e. an enumerable key-value map.
 */
class Dictionary implements IteratorAggregate, WritableMapInterface
{
    use StringTranslatingTrait;
    /** @var array<array-key, mixed> */
    protected $data;
    /**
     * @param array<array-key, mixed> $data The key-value map of data.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    /**
     * {@inheritDoc}
     */
    public function get($key)
    {
        if (!array_key_exists($key, $this->data)) {
            throw new NotFoundException($this->__('Dictionary does not have key "%1$s"', [$key]), 0, null);
        }
        return $this->data[$key];
    }
    /**
     * {@inheritDoc}
     */
    public function has($key)
    {
        $key = (string) $key;
        $isHas = array_key_exists($key, $this->data);
        return $isHas;
    }
    /**
     * {@inheritDoc}
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }
    /**
     * @inheritDoc
     * @psalm-suppress MoreSpecificReturnType
     * Psalm complains that the declared return type is more specific than inferred.
     * This is not true, as it promises to return the interface.
     */
    public function withMappings(array $mappings): WritableContainerInterface
    {
        $dictionary = $this->cloneMe();
        $dictionary->data = $mappings;
        /**
         * @psalm-suppress LessSpecificReturnStatement
         * Looks like this needs to be suppressed until able to hint return type `self`.
         */
        return $dictionary;
    }
    /**
     * @inheritDoc
     * @psalm-suppress MoreSpecificReturnType
     * Psalm complains that the declared return type is more specific than inferred.
     * This is not true, as it promises to return the interface.
     */
    public function withAddedMappings(array $mappings): WritableContainerInterface
    {
        $dictionary = $this->cloneMe();
        $dictionary->data = $mappings + $this->data;
        /**
         * @psalm-suppress LessSpecificReturnStatement
         * Looks like this needs to be suppressed until able to hint return type `self`.
         */
        return $dictionary;
    }
    /**
     * @inheritDoc
     * @psalm-suppress MoreSpecificReturnType
     * Psalm complains that the declared return type is more specific than inferred.
     * This is not true, as it promises to return the interface.
     */
    public function withoutKeys(array $keys): WritableContainerInterface
    {
        $dictionary = $this->cloneMe();
        foreach ($keys as $i => $key) {
            /** @psalm-suppress DocblockTypeContradiction Still want to enforce string */
            if (!is_string($key)) {
                throw new RangeException($this->__('Key at index %1$d is not a string', [$i]));
            }
            unset($dictionary->data[$key]);
        }
        /**
         * @psalm-suppress LessSpecificReturnStatement
         * Looks like this needs to be suppressed until able to hint return type `self`.
         */
        return $dictionary;
    }
    /**
     * Creates a copy of this instance
     *
     * @return Dictionary The new instance
     */
    protected function cloneMe(): \WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Dictionary
    {
        return clone $this;
    }
}
