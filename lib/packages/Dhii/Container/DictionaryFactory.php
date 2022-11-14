<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\WritableMapFactoryInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * @inheritDoc
 */
class DictionaryFactory implements WritableMapFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function createContainerFromArray(array $data): ContainerInterface
    {
        return new Dictionary($data);
    }
}
