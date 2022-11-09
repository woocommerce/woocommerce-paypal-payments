<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Collection;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerExceptionInterface;

/**
 * Something that can determine the existence of a key.
 */
interface HasCapableInterface
{
    /**
     * Determines whether this instance has the specified key.
     *
     * @param string $key The key to check for.
     *
     * @return bool True if the key exists; false otherwise.
     *
     * @throws ContainerExceptionInterface If problem determining.
     *
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress InvalidThrow
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    public function has($key);
}
