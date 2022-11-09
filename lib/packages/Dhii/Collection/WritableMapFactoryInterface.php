<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Collection;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface as BaseContainerInterface;

/**
 * Creates writable maps.
 *
 * @psalm-suppress UnusedClass
 */
interface WritableMapFactoryInterface extends WritableContainerFactoryInterface, MapFactoryInterface
{
    /**
     * @inheritDoc
     *
     * @return WritableMapInterface The new map.
     */
    public function createContainerFromArray(array $data): BaseContainerInterface;
}
