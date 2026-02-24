<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Collection;

/**
 * Creates writable sets.
 *
 * @psalm-suppress UnusedClass
 */
interface WritableSetFactoryInterface extends \WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\SetFactoryInterface
{
    /**
     * @inheritDoc
     *
     * @return WritableSetInterface The new writable set.
     */
    public function createSetFromList(array $list): \WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\SetInterface;
}
