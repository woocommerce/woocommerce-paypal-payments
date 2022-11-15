<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\ContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Util\StringTranslatingTrait;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface as PsrContainerInterface;

use function array_key_exists;

/**
 * A container implementation that wraps around an inner container to alias its keys, so consumers can use the aliases
 * to fetch data from the inner container.
 */
class AliasingContainer implements ContainerInterface
{
    /* @since [*next-version*] */
    use StringTranslatingTrait;

    /**
     * @since [*next-version*]
     *
     * @var PsrContainerInterface
     */
    protected $inner;

    /**
     * @since [*next-version*]
     *
     * @var array<array-key, string>
     */
    protected $aliases;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param PsrContainerInterface    $inner   The container whose keys to alias.
     * @param array<array-key, string> $aliases A mapping of aliases to their original container key counterparts.
     */
    public function __construct(PsrContainerInterface $inner, array $aliases)
    {
        $this->inner = $inner;
        $this->aliases = $aliases;
    }

    /**
     * @inheritdoc
     *
     * @since [*next-version*]
     */
    public function get($key)
    {
        return $this->inner->get($this->getInnerKey($key));
    }

    /**
     * @inheritdoc
     *
     * @since [*next-version*]
     */
    public function has($key)
    {
        return $this->inner->has($this->getInnerKey($key));
    }

    /**
     * Retrieves the key to use for the inner container.
     *
     * @since [*next-version*]
     *
     * @param string $key The outer key.
     *
     * @return string The inner key.
     */
    protected function getInnerKey(string $key): string
    {
        if (array_key_exists($key, $this->aliases)) {
            return $this->aliases[$key];
        }

        return $key;
    }
}
