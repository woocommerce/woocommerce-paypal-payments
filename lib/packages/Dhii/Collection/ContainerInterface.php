<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Collection;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface as BaseContainerInterface;

/**
 * Something that can retrieve and determine the existence of a value by key.
 */
interface ContainerInterface extends
    HasCapableInterface,
    BaseContainerInterface
{
}
