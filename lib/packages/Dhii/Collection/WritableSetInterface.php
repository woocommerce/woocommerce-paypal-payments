<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Collection;

use Exception;

/**
 * A set that can have items added.
 *
 * @psalm-suppress UnusedClass
 */
interface WritableSetInterface extends SetInterface
{
    /**
     * Creates a new instance with the given items only.
     *
     * @param array|mixed[] $items A list of items for the set.
     *
     * @return static A new instance of this class with only the given items.
     *
     * @throws Exception If problem creating.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function withItems(array $items);

    /**
     * Creates a new instance with the given items added to existing ones.
     *
     * @param array|mixed[] $items A list of items to add.
     *
     * @return static A new instance of this class with the given items added to existing ones.
     *
     * @throws Exception If problem creating.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function withAddedItems(array $items): WritableSetInterface;

    /**
     * Creates a new instance with the given items not present.
     *
     * @param array|mixed[] $items A list of items to exclude.
     *
     * @return static An instance of this class without the given items.
     *
     * @throws Exception If problem creating.
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function withoutItems(array $items): WritableSetInterface;
}
