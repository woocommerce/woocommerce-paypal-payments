<?php

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\ContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface as PsrContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\NotFoundExceptionInterface;

/**
 * A container implementation that wraps around an inner container to automatically add prefixes to keys during
 * fetching and look up, allowing consumers to omit them.
 *
 * @since [*next-version*]
 */
class DeprefixingContainer implements ContainerInterface
{
    /**
     * @since [*next-version*]
     *
     * @var PsrContainerInterface
     */
    protected $inner;

    /**
     * @since [*next-version*]
     *
     * @var string
     */
    protected $prefix;

    /**
     * @since [*next-version*]
     *
     * @var bool
     */
    protected $strict;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param PsrContainerInterface $container The container whose keys to deprefix.
     * @param string                $prefix    The prefix to remove from the container's keys.
     * @param bool                  $strict    Whether or not to fallback to prefixed keys if an un-prefixed key does
     *                                         not exist in the inner container.
     */
    public function __construct(PsrContainerInterface $container, string $prefix, bool $strict = true)
    {
        $this->inner = $container;
        $this->prefix = $prefix;
        $this->strict = $strict;
    }

    /**
     * @inheritdoc
     *
     * @since [*next-version*]
     */
    public function get($key)
    {
        /**
         * @psalm-suppress InvalidCatch
         * The base interface does not extend Throwable, but in fact everything that is possible
         * in theory to catch will be Throwable, and PSR-11 exceptions will implement this interface
         */
        try {
            return $this->inner->get($this->getInnerKey($key));
        } catch (NotFoundExceptionInterface $nfException) {
            if ($this->strict || !$this->inner->has($key)) {
                throw $nfException;
            }
        }

        return $this->inner->get($key);
    }

    /**
     * @inheritdoc
     *
     * @since [*next-version*]
     */
    public function has($key)
    {
        return $this->inner->has($this->getInnerKey($key)) || (!$this->strict && $this->inner->has($key));
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
        return $this->prefix . $key;
    }
}
