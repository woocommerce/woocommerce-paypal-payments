<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Collection;

use Exception;
/**
 * A factory that can create sets.
 *
 * @since [*next-version*]
 */
interface SetFactoryInterface
{
    /**
     * Creates a set based on data in a list.
     *
     * @since [*next-version*]
     *
     * @param array<mixed> $list The list to base the set on.
     *
     * @return SetInterface The new set.
     *
     * @throws Exception If problem creating.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function createSetFromList(array $list): \WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\SetInterface;
}
