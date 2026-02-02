<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\ContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\ContainerException;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Util\StringTranslatingTrait;
use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface as PsrContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\NotFoundExceptionInterface;
/**
 * Caches entries from an internal container.
 *
 * @package Dhii\Container1
 */
class CachingContainer implements ContainerInterface
{
    use StringTranslatingTrait;
    /** @var array<array-key, mixed> */
    protected $cache;
    /** @var PsrContainerInterface */
    protected $container;
    /**
     * @param PsrContainerInterface $container The container to cache entries from.
     */
    public function __construct(PsrContainerInterface $container)
    {
        $this->container = $container;
        $this->cache = [];
    }
    /**
     * {@inheritDoc}
     */
    public function get($key)
    {
        /** @psalm-suppress RedundantCastGivenDocblockType
         * @psalm-suppress RedundantCast
         * Will remove when switching to PHP 7.2 and new PSR-11 interfaces
         */
        $key = (string) $key;
        /**
         * @psalm-suppress InvalidCatch
         * The base interface does not extend Throwable, but in fact everything that is possible
         * in theory to catch will be Throwable, and PSR-11 exceptions will implement this interface
         */
        try {
            /**
             * @psalm-suppress MissingClosureReturnType
             * Unable to specify mixed before PHP 8.
             */
            $value = $this->getCached($key, function () use ($key) {
                return $this->container->get($key);
            });
        } catch (NotFoundExceptionInterface $e) {
            throw new NotFoundException($this->__('Key "%1$s" not found in inner container', [$key]), 0, $e);
        } catch (Exception $e) {
            throw new ContainerException($this->__('Could not retrieve value for key "%1$s" from inner container', [$key]), 0, $e);
        }
        return $value;
    }
    /**
     * {@inheritDoc}
     */
    public function has($key)
    {
        /** @psalm-suppress RedundantCastGivenDocblockType
         * Will remove when switching to PHP 7.2 and new PSR-11 interfaces
         */
        $key = (string) $key;
        /**
         * @psalm-suppress InvalidCatch
         * The base interface does not extend Throwable, but in fact everything that is possible
         * in theory to catch will be Throwable, and PSR-11 exceptions will implement this interface
         */
        try {
            if ($this->hasCached($key)) {
                return \true;
            }
        } catch (Exception $e) {
            throw new ContainerException($this->__('Could not check cache for key "%1$s"', [$key]), 0, $e);
        }
        try {
            if ($this->container->has($key)) {
                return \true;
            }
        } catch (Exception $e) {
            throw new ContainerException($this->__('Could not check inner container for key "%1$s"', [$key]), 0, $e);
        }
        return \false;
    }
    /**
     * Retrieves a value by key from cache, creating it if it does not exist.
     *
     * @param string $key The key to get.
     * @param callable $generator Creates the value.
     *
     * @return mixed The cached value.
     *
     * @throws Exception If problem caching.
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    protected function getCached(string $key, callable $generator)
    {
        if (!array_key_exists($key, $this->cache)) {
            $value = $this->invokeGenerator($generator);
            $this->cache[$key] = $value;
        }
        return $this->cache[$key];
    }
    /**
     * Checks the cache for the specified key.
     *
     * @param string $key The key to check for.
     *
     * @return bool True if cache contains the value; false otherwise.
     *
     * @throws Exception If problem checking.
     */
    protected function hasCached(string $key): bool
    {
        return array_key_exists($key, $this->cache);
    }
    /**
     * Generates a value by invoking the generator.
     *
     * @param callable $generator Generates a value.
     *
     * @return mixed The generated result.
     *
     * @throws Exception If problem generating.
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    protected function invokeGenerator(callable $generator)
    {
        $result = $generator();
        return $result;
    }
}
