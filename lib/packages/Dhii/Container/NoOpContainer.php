<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use ArrayIterator;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\ClearableContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\MutableContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\WritableContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\WritableMapInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\ContainerException;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\NotFoundException;
use IteratorAggregate;

/**
 * A container that does nothing.
 *
 * This can be used if an actual implementation is not available,
 * without extra checks or nullables - just as if it was a real one.
 */
class NoOpContainer implements
    MutableContainerInterface,
    IteratorAggregate,
    WritableMapInterface,
    ClearableContainerInterface
{
    /**
     * @inheritDoc
     */
    public function get($id)
    {
        throw new NotFoundException('NoOp container cannot have values');
    }

    /**
     * @inheritDoc
     */
    public function has($id)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $value): void
    {
        // Do nothing
    }

    /**
     * @inheritDoc
     */
    public function unset(string $key): void
    {
        throw new ContainerException('NoOp container cannot have values');
    }

    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        // Do nothing
    }

    /**
     * @inheritDoc
     */
    public function withMappings(array $mappings): WritableContainerInterface
    {
        return clone $this;
    }

    /**
     * @inheritDoc
     */
    public function withAddedMappings(array $mappings): WritableContainerInterface
    {
        return clone $this;
    }

    /**
     * @inheritDoc
     */
    public function withoutKeys(array $keys): WritableContainerInterface
    {
        return clone $this;
    }

    /**
     * @inheritDoc
     */
    public function getIterator()
    {
        return new ArrayIterator([]);
    }
}
