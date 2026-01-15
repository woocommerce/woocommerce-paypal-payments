<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Collection;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * Creates containers based on data maps.
 */
interface ContainerFactoryInterface
{
    /**
     * Creates a container based on data.
     *
     * @param array<string, mixed> $data The data for the container.
     *
     * @return ContainerInterface The new container.
     *
     * @throws Exception If problem creating.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function createContainerFromArray(array $data): ContainerInterface;
}
