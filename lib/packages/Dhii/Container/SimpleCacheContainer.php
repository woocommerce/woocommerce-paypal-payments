<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Vendor\Dhii\Container;

use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\ClearableContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Collection\MutableContainerInterface;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\ContainerException;
use WooCommerce\PayPalCommerce\Vendor\Dhii\Container\Exception\NotFoundException;
use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\SimpleCache\CacheInterface;
class SimpleCacheContainer implements MutableContainerInterface, ClearableContainerInterface
{
    /**
     * @var CacheInterface
     */
    protected $storage;
    /**
     * @var int
     */
    protected $ttl;
    public function __construct(CacheInterface $storage, int $ttl)
    {
        $this->storage = $storage;
        $this->ttl = $ttl;
    }
    /**
     * @inheritDoc
     */
    public function get($id)
    {
        $storage = $this->storage;
        try {
            if (!$storage->has($id)) {
                return new NotFoundException(sprintf('Key "%1$s" not found', $id));
            }
            $value = $storage->get($id);
        } catch (Exception $e) {
            throw new ContainerException(sprintf('Could not retrieve value for key "%1$s"', $id), 0, $e);
        }
        return $value;
    }
    /**
     * @inheritDoc
     */
    public function has($id)
    {
        $id = (string) $id;
        $storage = $this->storage;
        try {
            $has = $storage->has($id);
        } catch (Exception $e) {
            throw new ContainerException(sprintf('Could not check for key "%1$s"', $id), 0, $e);
        }
        return $has;
    }
    /**
     * @inheritDoc
     */
    public function set(string $key, $value): void
    {
        $storage = $this->storage;
        $ttl = $this->ttl;
        try {
            $storage->set($key, $value, $ttl);
        } catch (Exception $e) {
            throw new ContainerException(sprintf('Could not set key "%1$s" with value "%2$s"', $key, (string) $value), 0, $e);
        }
    }
    /**
     * @inheritDoc
     */
    public function unset(string $key): void
    {
        $storage = $this->storage;
        try {
            $storage->delete($key);
        } catch (Exception $e) {
            throw new ContainerException(sprintf('Could not unset key "%1$s"', $key), 0, $e);
        }
    }
    /**
     * @inheritDoc
     */
    public function clear(): void
    {
        $storage = $this->storage;
        try {
            $storage->clear();
        } catch (Exception $e) {
            throw new ContainerException('Could not clear container', 0, $e);
        }
    }
}
