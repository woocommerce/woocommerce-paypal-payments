<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Collection;

use Exception;
/**
 * A container that can be written to.
 */
interface WritableContainerInterface extends \WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\ContainerInterface
{
    /**
     * Creates a new instance with the specified mappings.
     *
     * @since [*next-version*]
     *
     * @param array<string, mixed> $mappings A map of keys to values.
     *
     * @return static A new instance of this class with only the specified key-value mappings.
     *
     * @throws Exception If problem creating.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function withMappings(array $mappings): \WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\WritableContainerInterface;
    /**
     * Creates a new instance with the specified mappings added to existing ones.
     *
     * @since [*next-version*]
     *
     * @param array<string, mixed> $mappings A map of keys to values.
     *
     * @return static A new instance of this class with the specified key-value mappings added to existing ones.
     *
     * @throws Exception If problem creating.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function withAddedMappings(array $mappings): \WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\WritableContainerInterface;
    /**
     * Creates a new instance with the specified keys not present.
     *
     * @since [*next-version*]
     *
     * @param array<string> $keys The keys to exclude.
     *
     * @return static A new instance of this class which does not contain the specified keys.
     *
     * @throws Exception If problem instantiating.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function withoutKeys(array $keys): \WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\WritableContainerInterface;
}
