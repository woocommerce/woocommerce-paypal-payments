<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Collection;

/**
 * A countable list that can be checked for a key.
 *
 * @since 0.2
 *
 * @psalm-suppress UnusedClass
 */
interface CountableSetInterface extends
    /* @since 0.2 */
    CountableListInterface,
    /* @since 0.2 */
    SetInterface
{

}
