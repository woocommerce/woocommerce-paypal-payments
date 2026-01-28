<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Collection;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface as BaseContainerInterface;
/**
 * A factory that can create maps.
 *
 * @since 0.2
 */
interface MapFactoryInterface extends \WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\ContainerFactoryInterface
{
    /**
     * Creates a map based on data in an array.
     *
     * @param array<string, mixed> $data The data to base the map on.
     *
     * @return MapInterface The new map.
     *
     * @throws Exception If problem creating.
     */
    public function createContainerFromArray(array $data): BaseContainerInterface;
}
