<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Collection;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerExceptionInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\NotFoundExceptionInterface;
/**
 * A container that can have mappings added and removed.
 *
 * @psalm-suppress UnusedClass
 */
interface MutableContainerInterface extends \WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\ContainerInterface
{
    /**
     * Maps the given value to the specified key.
     *
     * @since [*next-version*]
     *
     * @param string $key   The key to map the value to.
     * @param mixed  $value The value to map to the key.
     *
     * @throws ContainerExceptionInterface If problem mapping.
     * @psalm-suppress InvalidThrow
     */
    public function set(string $key, $value): void;
    /**
     * Unmaps the value from the specified key.
     *
     * @since [*next-version*]
     *
     * @param string $key The key to unmap the value from.
     *
     * @throws NotFoundExceptionInterface  If key not found.
     * @throws ContainerExceptionInterface If problem unmapping.
     * @psalm-suppress InvalidThrow
     */
    public function unset(string $key): void;
}
